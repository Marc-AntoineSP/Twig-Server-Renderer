<?php

namespace App\Controller;

use Styleguide\Contracts\CatalogueException;
use App\Entity\CatalogueItem;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Form\ThemeType;
use App\Theme\Contrast;
use App\Theme\ThemeFactory;
use App\Theme\ThemeFormData;
use App\Theme\ThemeSample;
use App\Theme\ThemeComponentShowcase;
use App\Theme\ThemeWriter;
use App\Appearance\ThemeFamilies;
use App\Appearance\ThemeFamilyWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[IsGranted('ROLE_READER')]
final class ThemeController extends AbstractController
{
    #[Route('/themes', name: 'theme_index', methods: ['GET'])]
    public function index(\App\Theme\ThemeCatalogue $catalogue, #[\Symfony\Component\HttpKernel\Attribute\MapQueryString(validationFailedStatusCode: 400)] \App\Theme\ThemeSearchQuery $query = new \App\Theme\ThemeSearchQuery()): Response
    {
        return $this->render('theme/index.html.twig', $catalogue->search($query));
    }

    #[Route('/themes/import', name: 'theme_import', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function import(): Response { return $this->render('theme/import.html.twig'); }

    #[Route('/themes/new', name: 'theme_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function create(Request $request, ThemeFactory $factory, ThemeFormData $data, ThemeFamilyWriter $writer, ThemeSample $sample, EntityManagerInterface $entityManager, \App\Font\FontLibrary $fonts): Response
    {
        $document = $factory->create();
        $document->name = '';
        $document->cssName = '';
        $form = $this->createForm(ThemeType::class, $data->array($document));
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $theme = $writer->create($data->document($form->getData(), $document->fonts), $form->get('startingVariants')->getData(), $form->get('familyLabel')->getData() ?: $form->get('name')->getData());
                $this->addFlash('success', 'Theme created.');

                return $this->redirectToRoute('theme_edit', ['id' => $theme->getId()], 303);
            } catch (CatalogueException $error) { $form->addError(new FormError($error->getMessage())); $status = $error->getStatusCode(); }
        }

        $sampleItem = $sample->item();
        return $this->render('theme/edit.html.twig', ['creating' => true, 'theme' => null, 'document' => $document, 'family' => ['variants' => (object) []], 'form' => $form, 'ratios' => [], 'sampleId' => $sampleItem->getId(), 'themes' => $entityManager->getRepository(Theme::class)->findBy([], ['name' => 'ASC']), 'components' => [$sampleItem], 'fontDefinitions' => array_map(fn ($id) => $fonts->definition($id), array_keys($fonts->catalogue())), 'families' => $fonts->catalogue()], new Response(status: $status));
    }

    #[Route('/themes/{id}', name: 'theme_show', methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Theme $theme, EntityManagerInterface $entityManager, Contrast $contrast, ThemeComponentShowcase $showcase): Response
    {
        $document = null; $error = null;
        try { $document = $showcase->presentation($theme); }
        catch (CatalogueException) { $error = 'A selected font asset is unavailable. Restore its local files or choose another theme variant, then reload the showcase.'; }
        return $this->render('theme/show.html.twig', ['theme' => $theme, 'showcase' => $document, 'showcaseError' => $error, 'ratios' => $contrast->ratios($theme->getDocument()), 'releases' => $entityManager->getRepository(ThemeRelease::class)->findBy(['theme' => $theme], ['createdAt' => 'DESC'])]);
    }

    #[Route('/themes/{id}/showcase/{hash}.css', name: 'theme_showcase_css', requirements: ['hash' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function showcaseCss(#[MapEntity(id: 'id')] Theme $theme, string $hash, ThemeComponentShowcase $showcase): Response
    {
        $presentation = $showcase->presentation($theme);
        if (!hash_equals($presentation['hash'], $hash)) throw $this->createNotFoundException('This theme stylesheet has changed. Reload the detail page.');

        return new Response($presentation['css'], headers: ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    #[Route('/themes/{id}/duplicate', name: 'theme_duplicate', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('theme-duplicate', tokenKey: '_token')]
    public function duplicate(#[MapEntity(id: 'id')] Theme $theme, ThemeFamilyWriter $writer): Response
    {
        $copy = $writer->duplicate($theme);
        $this->addFlash('success', 'Independent theme copy created. The reference preset is preserved.');

        return $this->redirectToRoute('theme_edit', ['id' => $copy->getId()], 303);
    }

    #[Route('/themes/{id}/variants', name: 'theme_add_variant', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('theme-variant', tokenKey: '_token')]
    public function addVariant(#[MapEntity(id: 'id')] Theme $theme, ThemeFamilyWriter $writer): Response
    {
        $created = $writer->addVariant($theme);
        $this->addFlash('success', 'Variant created from the complete default palette; customize and apply when ready.');
        return $this->redirectToRoute('theme_edit', ['id' => $created->getId()], 303);
    }

    #[Route('/themes/{id}/apply', name: 'theme_apply', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('theme-apply', tokenKey: '_token')]
    public function apply(#[MapEntity(id: 'id')] Theme $theme, EntityManagerInterface $em, ThemeSample $sample, \App\Rendering\RendererPresets $presets): Response
    {
        $release = $em->getRepository(ThemeRelease::class)->findOneBy(['theme' => $theme], ['createdAt' => 'DESC']) ?? throw new CatalogueException(422, 'Publish a theme release before applying it.');
        $item = $sample->item(); $tokens = $release->getDocument();
        $settings = (array) $presets->read($item, $this->getUser());
        $settings = [...$settings, 'themeReleaseId' => $release->getId(), 'bodyFont' => 'theme', 'headingFont' => 'theme', 'textClass' => $tokens->typography->textClass, 'rootSize' => $tokens->typography->rootFontSizePx];
        $presets->save($item, $this->getUser(), new \App\Rendering\RendererSettings(...$settings));

        return $this->redirectToRoute('catalogue_edit', ['id' => $item->getId()], 303);
    }

    #[Route('/themes/{id}/edit', name: 'theme_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function edit(Request $request, #[MapEntity(id: 'id')] Theme $theme, ThemeFormData $data, ThemeWriter $writer, ThemeSample $sample, EntityManagerInterface $entityManager, Contrast $contrast, \App\Font\FontLibrary $fonts, ThemeFamilies $themeFamilies, ThemeFamilyWriter $familyWriter): Response
    {
        $document = $theme->getDocument();
        $family = $themeFamilies->forTheme($theme);
        if ($family->isBuiltIn() || ($document->provenance->managed ?? false)) return $this->redirectToRoute('theme_show', ['id' => $theme->getId()]);
        $recent = $request->getSession()->get('recent_theme_creations', []);
        if (isset($recent[$theme->getId()])) { unset($recent[$theme->getId()]); $request->getSession()->set('recent_theme_creations', $recent); $this->addFlash('success', 'Theme created.'); }
        $sampleItem = $sample->item();
        $form = $this->createForm(ThemeType::class, $data->array($document), ['existing' => true, 'revision' => $theme->getRevision(), 'family_label' => $family->getLabel(), 'font_ids' => array_map(static fn (object $font): string => $font->id, $document->fonts)]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $familyWriter->rename($family, $form->get('familyLabel')->getData());
                $writer->save($theme, (int) $form->get('revision')->getData(), $data->document($form->getData(), $document->fonts, $document));
                $this->addFlash('success', 'Theme saved.');

                return $this->redirectToRoute('theme_edit', ['id' => $theme->getId()], 303);
            } catch (CatalogueException $error) { $form->addError(new FormError($error->getMessage())); $status = $error->getStatusCode(); }
        }

        $components=$entityManager->getRepository(CatalogueItem::class)->createQueryBuilder('i')->where('i.kind IN (:kinds)')->setParameter('kinds',['component','block','layout'])->orderBy('i.title','ASC')->setMaxResults(200)->getQuery()->getResult();
        if(!array_filter($components,static fn($i)=>$i->getId()===$sampleItem->getId()))$components[]=$sampleItem;
        return $this->render('theme/edit.html.twig', ['creating' => false, 'theme' => $theme, 'document' => $document, 'family' => $themeFamilies->describe($family), 'form' => $form, 'ratios' => $contrast->ratios($document), 'sampleId' => $sampleItem->getId(), 'themes' => $entityManager->getRepository(Theme::class)->findBy([], ['name' => 'ASC']), 'components' => $components, 'fontDefinitions' => [...array_map(fn ($id) => $fonts->definition($id), array_keys($fonts->catalogue())), ...$document->fonts], 'families' => $fonts->catalogue()], new Response(status: $status));
    }
}
