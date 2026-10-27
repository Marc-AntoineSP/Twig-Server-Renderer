<?php
namespace App\Controller;
use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\DraftInput;
use App\Composition\CompositionCompiler;
use App\Composition\CompositionInput;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use Styleguide\Contracts\CanonicalJson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
#[IsGranted('ROLE_EDITOR')]
final class CompositionController extends AbstractController
{
    #[Route('/catalogue/{id}/compose', name: 'composition_edit', methods: ['GET'])]
    public function edit(#[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer, EntityManagerInterface $em): Response
    {
        $draft = $writer->draft($item); $source = $draft->getSourceMap()->{'meta/composition.json'} ?? null;
        if (null === $source) throw new CatalogueException(409, 'This item uses source ownership; the composition builder is disabled.');
        $choices = [];
        foreach ($em->getRepository(Release::class)->findBy([], ['createdAt' => 'DESC'], 200) as $release) {
            if ('theme' === $release->getItem()->getKind()) continue;
            $m = $release->getManifestData();
            $choices[] = ['name' => $m->name, 'version' => $m->version, 'label' => $m->name.'@'.$m->version, 'props' => $m->defaults];
        }
        return $this->render('composition/edit.html.twig', ['item' => $item, 'draft' => $draft, 'document' => json_decode($source, false, 64, JSON_THROW_ON_ERROR), 'choices' => $choices, 'generatedTwig' => $draft->getSourceMap()->{$draft->getManifestData()->entry}]);
    }
    #[Route('/api/v1/items/{id}/composition', name: 'composition_save', methods: ['PUT', 'POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function save(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 64, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] CompositionInput $input, CatalogueWriter $writer, CompositionCompiler $compiler): JsonResponse
    {
        $draft = $writer->draft($item);
        if (!isset($draft->getSourceMap()->{'meta/composition.json'})) throw new CatalogueException(409, 'This item uses source ownership.');
        if ($draft->getRevision() !== $input->expectedRevision) throw new CatalogueException(412, 'Composition changed. Reload and merge your tree.');
        [$manifest, $sources] = $compiler->materialize($draft->getManifestData(), $draft->getSourceMap(), $input->document);
        if ('PUT' === $request->getMethod()) {
            $writer->save($item, $input->expectedRevision, new DraftInput($manifest, $sources, $input->title ?? $item->getTitle(), $input->description ?? $item->getDescription(), $item->getTags()), $this->getUser());
        }
        return new JsonResponse(['revision' => $draft->getRevision(), 'manifestData' => $manifest, 'sourceMap' => $sources]);
    }
    #[Route('/catalogue/{id}/convert-source', name: 'composition_convert', methods: ['POST'])]
    #[IsCsrfTokenValid('convert-source', tokenKey: '_token')]
    public function convert(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer): Response
    {
        $draft = $writer->draft($item); $manifest = $draft->getManifestData(); $old = $item->getName(); $new = $request->request->getString('name');
        if (!isset($draft->getSourceMap()->{'meta/composition.json'})) throw new CatalogueException(409, 'This item already uses source ownership.');
        $sources = new \stdClass();
        foreach ($draft->getSourceMap() as $path => $content) {
            if ('meta/composition.json' === $path) continue;
            $renamed = str_replace(['catalogue/'.$old.'/', 'catalogue/'.$old.'_controller.js'], ['catalogue/'.$new.'/', 'catalogue/'.$new.'_controller.js'], $path);
            if (str_ends_with($path, '.twig') || str_ends_with($path, '.js')) $content = str_replace(['catalogue/'.$old.'/', 'catalogue--'.str_replace('/', '--', $old)], ['catalogue/'.$new.'/', 'catalogue--'.str_replace('/', '--', $new)], $content);
            $sources->$renamed = $content;
        }
        $manifest->name = $new; $manifest->files = []; $manifest->entry = str_replace('catalogue/'.$old.'/', 'catalogue/'.$new.'/', $manifest->entry);
        foreach ($manifest->controllers as $controller) {
            $controller->path = str_replace(['catalogue/'.$old.'/', 'catalogue/'.$old.'_controller.js'], ['catalogue/'.$new.'/', 'catalogue/'.$new.'_controller.js'], $controller->path);
            $controller->identifier = str_replace('catalogue--'.str_replace('/', '--', $old), 'catalogue--'.str_replace('/', '--', $new), $controller->identifier);
        }
        $created = $writer->create(new CreateItemInput($new, $item->getKind(), mb_substr($item->getTitle(), 0, 111).' (source)', $item->getDescription(), $item->getTags()), $this->getUser(), $manifest, $sources);
        $this->addFlash('success', 'Duplicated into source mode. Original composition preserved.');
        return $this->redirectToRoute('catalogue_edit', ['id' => $created->getItem()->getId()], 303);
    }
}
