<?php

namespace App\Controller;

use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\FormInput;
use App\Catalogue\SearchQuery;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Entity\ThemeRelease;
use App\Entity\Theme;
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
    public function index(CatalogueRepository $catalogue, \App\Rendering\ReleaseThumbnails $thumbnails, \App\Favorites\Favorites $favorites, \App\Discovery\ListingResponse $listing, #[MapQueryString(validationFailedStatusCode: 400)] SearchQuery $query = new SearchQuery()): Response
    {
        $result=$catalogue->search($query, $favorites->owner());
        $result['hasFavorites'] = $favorites->hasCatalogueFavorites();
        foreach($result['summaries'] as &$summary)$summary['hasThumbnail']=$thumbnails->available($summary['archive_hash']);unset($summary);
        return $listing->render('catalogue_index', 'catalogue/index.html.twig', $result);
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
    public function show(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer, EntityManagerInterface $entityManager, \App\Rendering\CataloguePreviews $previews): Response
    {
        $draft = $writer->draft($item);
        $releases = $entityManager->getRepository(Release::class)->findBy(['item' => $item], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $selected = $request->query->getString('release', isset($releases[0]) ? $releases[0]->getId() : 'draft');
        if ('draft' === $selected && $request->query->has('release') && !$this->isGranted('ROLE_EDITOR')) throw new CatalogueException(403, 'Draft previews require editor access.');
        $snapshot = null;
        if ('theme' !== $item->getKind() && ('draft' !== $selected || $this->isGranted('ROLE_EDITOR'))) {
            $snapshot = $previews->snapshot($item, 'draft' === $selected ? null : $selected, 'draft' === $selected ? $draft->getRevision() : null, $this->isGranted('ROLE_EDITOR'));
        }
        $example = $request->query->getInt('example', -1);
        if ($example < -1 || ($example >= 0 && !isset($snapshot['examples'][$example]))) throw new CatalogueException(422, 'Choose an example from this exact source version.');
        $themeId = $request->query->getString('theme');
        $theme = '' !== $themeId ? $entityManager->find(ThemeRelease::class, $themeId) : null;
        if ('' !== $themeId && !$theme) throw new CatalogueException(404, 'Theme release not found.');
        $settings = new \App\Rendering\RendererSettings(width: 768, bodyFont: 'theme', headingFont: 'theme', props: $snapshot ? ($example >= 0 ? $snapshot['examples'][$example]->props : $snapshot['manifest']->defaults) : null, themeReleaseId: $theme?->getId());
        $themeChoices = $entityManager->getRepository(ThemeRelease::class)->findBy([], ['createdAt' => 'DESC'], 100);
        if ($theme && !in_array($theme, $themeChoices, true)) $themeChoices[] = $theme;
        return $this->render('catalogue/show.html.twig', [
            'themeTarget' => $item->getKind() === 'theme' ? $entityManager->getRepository(Theme::class)->findOneBy(['name' => $item->getName()]) : null,
            'item' => $item, 'draft' => $draft, 'sourceFiles' => (array) ($snapshot['sources'] ?? $draft->getSourceMap()),
            'releases' => $releases, 'exportThemes' => $themeChoices, 'snapshot' => $snapshot,
            'selectedRelease' => $selected, 'selectedExample' => $example, 'rendererSettings' => $settings,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/catalogue/{id}/edit', name: 'catalogue_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EDITOR')]
    public function edit(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer, FormInput $input, RendererPresets $presets, EntityManagerInterface $entityManager, \App\Font\FontLibrary $fonts): Response
    {
        $draft = $writer->draft($item);
        if ('theme' === $item->getKind()) {
            $theme = $entityManager->getRepository(Theme::class)->findOneBy(['name' => $item->getName()]) ?? throw new CatalogueException(409, 'Typed theme draft not found.');
            return $this->redirectToRoute('theme_edit', ['id' => $theme->getId()]);
        }
        if (isset($draft->getSourceMap()->{'meta/composition.json'})) {
            return $this->redirectToRoute('composition_edit', ['id' => $item->getId(), 'returnTo' => $request->query->get('returnTo', '')]);
        }
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

                return $this->redirectToRoute('catalogue_edit', ['id' => $item->getId(), ...($request->query->has('returnTo') ? ['returnTo' => $request->query->get('returnTo')] : [])], 303);
            } catch (CatalogueException $error) {
                $status = $error->getStatusCode();
                $this->errors($form, $error);
            }
        }

        $rendererSettings=$presets->read($item,$this->getUser());
        $themeChoices = [];
        foreach ($entityManager->getRepository(ThemeRelease::class)->findBy([], ['createdAt' => 'DESC'],200) as $release) {
            $themeChoices[$release->getId()] = ['label' => $release->getTheme()->getName().'@'.$release->getVersion(), 'document' => $release->getDocument()];
        }

        if(($rendererSettings->themeReleaseId??null)&&!isset($themeChoices[$rendererSettings->themeReleaseId])){if($selected=$entityManager->find(ThemeRelease::class,$rendererSettings->themeReleaseId))$themeChoices[$selected->getId()]=['label'=>$selected->getTheme()->getName().'@'.$selected->getVersion(),'document'=>$selected->getDocument()];}

        return $this->render('catalogue/edit.html.twig', ['item' => $item, 'draft' => $draft, 'form' => $form, 'rendererSettings' => $rendererSettings, 'themeChoices' => $themeChoices, 'fontFamilies' => $fonts->catalogue()], new Response(status: $status));
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
