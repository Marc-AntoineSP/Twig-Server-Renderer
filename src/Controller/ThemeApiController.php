<?php

namespace App\Controller;

use Styleguide\Contracts\CatalogueException;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ThemeImporter;
use App\Theme\ThemeImportInput;
use App\Theme\ThemeUpdateInput;
use App\Theme\ThemeWriter;
use App\Theme\ThemePublishInput;
use App\Appearance\ThemeFamilies;
use App\Appearance\ThemeFamilyWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[IsGranted('ROLE_READER')]
final class ThemeApiController extends AbstractController
{
    #[Route('/api/v1/themes/contrast', name: 'theme_contrast', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function contrast(#[MapRequestPayload(acceptFormat: 'json', serializationContext: [\Symfony\Component\Serializer\Encoder\JsonDecode::ASSOCIATIVE => false, \Symfony\Component\Serializer\Encoder\JsonDecode::RECURSION_DEPTH => 12, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] \App\Theme\ContrastInput $input, \App\Theme\Contrast $contrast, \App\Theme\ContrastPreview $preview): JsonResponse
    {
        $keys = \Styleguide\Contracts\ThemeValidator::COLORS;
        if (count(get_object_vars($input->colors)) > count($keys)) throw new CatalogueException(422, 'Only semantic colour tokens are accepted.');
        foreach ($input->colors as $key => $value) if (!in_array($key, $keys, true) || !is_string($value) || strlen($value) > 100) throw new CatalogueException(422, 'Colour drafts must be bounded semantic token strings.');
        $results = $contrast->evaluate($input->colors);
        return new JsonResponse(['results' => $results, 'html' => $preview->document($input->colors, $results)], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/themes/import', name: 'theme_import_api', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function import(#[MapRequestPayload(acceptFormat: 'json', serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] ThemeImportInput $input, ThemeImporter $importer, ThemeWriter $writer, EntityManagerInterface $entityManager, CanonicalJson $json): JsonResponse
    {
        $document = $importer->parse($input->content, $input->format);
        if ('validate' === $input->mode) { return new JsonResponse(['valid' => true, 'document' => $document, 'canonicalJson' => $json->encode($document)]); }
        $existing = $entityManager->getRepository(Theme::class)->findOneBy(['name' => $document->name]);
        if ($existing && !$input->expectedRevision) { throw new CatalogueException(428, 'An existing theme requires its expected revision before import.'); }
        $theme = $existing ? $writer->save($existing, $input->expectedRevision, $document) : $writer->create($document);

        return new JsonResponse(['id' => $theme->getId(), 'revision' => $theme->getRevision(), 'document' => $theme->getDocument()], $existing ? 200 : 201);
    }

    #[Route('/api/v1/themes', name: 'theme_create_api', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function create(Request $request, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 24, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] ThemeUpdateInput $input, ThemeFamilyWriter $writer, ThemeFamilies $families): JsonResponse
    {
        $theme = $writer->create($input->document, $input->variants, $input->familyLabel);
        $recent = $request->getSession()->get('recent_theme_creations', []); $recent[$theme->getId()] = true;
        $request->getSession()->set('recent_theme_creations', array_slice($recent, -8, null, true));

        return new JsonResponse(['id' => $theme->getId(), 'revision' => $theme->getRevision(), 'document' => $theme->getDocument(), 'family' => $families->describe($families->forTheme($theme))], 201);
    }

    #[Route('/api/v1/themes/{id}', name: 'theme_read_api', methods: ['GET'], format: 'json')]
    public function read(#[MapEntity(id: 'id')] Theme $theme, ThemeFamilies $families): JsonResponse
    {
        return new JsonResponse(['id' => $theme->getId(), 'revision' => $theme->getRevision(), 'document' => $theme->getDocument(), 'family' => $families->describe($families->forTheme($theme))], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/themes/{id}', name: 'theme_save_api', methods: ['PUT'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function save(#[MapEntity(id: 'id')] Theme $theme, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 24, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] ThemeUpdateInput $input, ThemeWriter $writer, ThemeFamilies $families, ThemeFamilyWriter $familyWriter, EntityManagerInterface $em): JsonResponse
    {
        if (!$input->expectedRevision) { throw new CatalogueException(428, 'Theme revision is required.'); }
        $family = $families->ensureForTheme($theme);
        if (null !== $input->familyLabel && $input->familyLabel !== $family->getLabel() && null !== $input->expectedFamilyRevision && $input->expectedFamilyRevision !== $family->getRevision()) throw new CatalogueException(412, 'Family name changed. Reload before renaming it.');
        $familyWriter->rename($family, $input->familyLabel, $input->expectedFamilyRevision);
        $writer->save($theme, $input->expectedRevision, $input->document);

        return $this->read($theme, $families);
    }

    #[Route('/api/v1/themes/{id}/releases', name: 'theme_publish_api', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function publish(#[MapEntity(id: 'id')] Theme $theme, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] ThemePublishInput $input, ThemeWriter $writer): JsonResponse
    {
        if (!$input->expectedRevision) { throw new CatalogueException(428, 'Theme revision is required.'); }
        $release = $writer->publish($theme, $input->expectedRevision);

        return new JsonResponse(['id' => $release->getId(), 'version' => $release->getVersion()], 201);
    }
}
