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
use App\Theme\ThemeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_READER')]
final class ThemeController extends AbstractController
{
    #[Route('/themes', name: 'theme_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('theme/index.html.twig', ['themes' => $entityManager->getRepository(Theme::class)->findBy([], ['updatedAt' => 'DESC'])]);
    }

    #[Route('/themes/import', name: 'theme_import', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function import(): Response { return $this->render('theme/import.html.twig'); }

    #[Route('/themes/new', name: 'theme_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function create(Request $request, ThemeFactory $factory, ThemeFormData $data, ThemeWriter $writer): Response
    {
        $document = $factory->create();
        $document->name = '';
        $document->cssName = '';
        $form = $this->createForm(ThemeType::class, $data->array($document));
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $theme = $writer->create($data->document($form->getData(), $document->fonts));
                $this->addFlash('success', 'Theme created.');

                return $this->redirectToRoute('theme_edit', ['id' => $theme->getId()], 303);
            } catch (CatalogueException $error) { $form->addError(new FormError($error->getMessage())); $status = $error->getStatusCode(); }
        }

        return $this->render('theme/new.html.twig', ['form' => $form], new Response(status: $status));
    }

    #[Route('/themes/{id}', name: 'theme_show', methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Theme $theme, EntityManagerInterface $entityManager, Contrast $contrast): Response
    {
        return $this->render('theme/show.html.twig', ['theme' => $theme, 'ratios' => $contrast->ratios($theme->getDocument()), 'releases' => $entityManager->getRepository(ThemeRelease::class)->findBy(['theme' => $theme], ['createdAt' => 'DESC'])]);
    }

    #[Route('/themes/{id}/edit', name: 'theme_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function edit(Request $request, #[MapEntity(id: 'id')] Theme $theme, ThemeFormData $data, ThemeWriter $writer, ThemeSample $sample, EntityManagerInterface $entityManager, Contrast $contrast): Response
    {
        $document = $theme->getDocument();
        $sampleItem = $sample->item();
        $form = $this->createForm(ThemeType::class, $data->array($document), ['existing' => true, 'revision' => $theme->getRevision(), 'font_ids' => array_map(static fn (object $font): string => $font->id, $document->fonts)]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $writer->save($theme, (int) $form->get('revision')->getData(), $data->document($form->getData(), $document->fonts));
                $this->addFlash('success', 'Theme saved.');

                return $this->redirectToRoute('theme_edit', ['id' => $theme->getId()], 303);
            } catch (CatalogueException $error) { $form->addError(new FormError($error->getMessage())); $status = $error->getStatusCode(); }
        }

        $components=$entityManager->getRepository(CatalogueItem::class)->createQueryBuilder('i')->where('i.kind IN (:kinds)')->setParameter('kinds',['component','block','layout'])->orderBy('i.title','ASC')->setMaxResults(200)->getQuery()->getResult();
        if(!array_filter($components,static fn($i)=>$i->getId()===$sampleItem->getId()))$components[]=$sampleItem;
        return $this->render('theme/edit.html.twig', ['theme' => $theme, 'form' => $form, 'ratios' => $contrast->ratios($document), 'sampleId' => $sampleItem->getId(), 'themes' => $entityManager->getRepository(Theme::class)->findBy([], ['name' => 'ASC']), 'components' => $components], new Response(status: $status));
    }
}
