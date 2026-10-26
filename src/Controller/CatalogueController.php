<?php

namespace App\Controller;

use App\Catalogue\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\FormInput;
use App\Catalogue\SearchQuery;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Form\DraftType;
use App\Form\NewItemType;
use App\Repository\CatalogueRepository;
use App\Rendering\RendererPresets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_READER')]
final class CatalogueController extends AbstractController
{
    #[Route('/catalogue', name: 'catalogue_index', methods: ['GET'])]
    public function index(CatalogueRepository $catalogue, #[MapQueryString(validationFailedStatusCode: 400)] SearchQuery $query = new SearchQuery()): Response
    {
        return $this->render('catalogue/index.html.twig', $catalogue->search($query));
    }

    #[Route('/catalogue/new', name: 'catalogue_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function create(Request $request, CatalogueWriter $writer, FormInput $input): Response
    {
        $form = $this->createForm(NewItemType::class);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $draft = $writer->create($input->create($form->getData()), $this->getUser());
                $this->addFlash('success', 'Draft created.');

                return $this->redirectToRoute('catalogue_edit', ['id' => $draft->getItem()->getId()], 303);
            } catch (CatalogueException $error) {
                $status = $error->getStatusCode();
                $this->errors($form, $error);
            }
        }

        return $this->render('catalogue/new.html.twig', ['form' => $form], new Response(status: $status));
    }

    #[Route('/catalogue/{id}', name: 'catalogue_show', methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer, EntityManagerInterface $entityManager): Response
    {
        return $this->render('catalogue/show.html.twig', [
            'item' => $item, 'draft' => $writer->draft($item), 'sourceFiles' => (array) $writer->draft($item)->getSourceMap(),
            'releases' => $entityManager->getRepository(Release::class)->findBy(['item' => $item], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/catalogue/{id}/edit', name: 'catalogue_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function edit(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer, FormInput $input, RendererPresets $presets): Response
    {
        $draft = $writer->draft($item);
        $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $form = $this->createForm(DraftType::class, [
            'title' => $item->getTitle(), 'description' => $item->getDescription(), 'tagsText' => implode(', ', $item->getTags()),
            'revision' => $draft->getRevision(), 'manifestJson' => json_encode($draft->getManifestData(), $jsonOptions), 'sourceMapJson' => json_encode($draft->getSourceMap(), $jsonOptions),
        ]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $writer->save($item, (int) $data['revision'], $input->draft($data), $this->getUser());
                $this->addFlash('success', 'Draft saved.');

                return $this->redirectToRoute('catalogue_edit', ['id' => $item->getId()], 303);
            } catch (CatalogueException $error) {
                $status = $error->getStatusCode();
                $this->errors($form, $error);
            }
        }

        return $this->render('catalogue/edit.html.twig', ['item' => $item, 'draft' => $draft, 'form' => $form, 'rendererSettings' => $presets->read($item, $this->getUser())], new Response(status: $status));
    }

    private function errors(FormInterface $form, CatalogueException $error): void
    {
        $form->addError(new FormError($error->getMessage()));
        foreach ($error->fieldErrors as $path => $messages) {
            foreach ((array) $messages as $message) {
                $form->addError(new FormError($path.': '.$message));
            }
        }
    }
}
