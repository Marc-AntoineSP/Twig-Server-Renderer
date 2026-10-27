<?php

namespace App\Theme;
use Styleguide\Contracts\ThemeValidator;
use Styleguide\Contracts\ThemeCssGenerator;

use Styleguide\Contracts\CatalogueException;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Rendering\WorkerClient;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final readonly class ThemeWriter
{
    public function __construct(private EntityManagerInterface $entityManager, private ThemeValidator $validator, private ThemeCssGenerator $css, private WorkerClient $worker, private \App\Repository\CatalogueRepository $catalogue, private \App\Font\ThemeFontAssets $fontAssets, private \App\Appearance\ThemeFamilies $families)
    {
    }

    public function create(\stdClass $document, ?\App\Entity\ThemeFamily $family = null): Theme
    {
        $this->validator->validate($document);
        $this->fontAssets->files($document);
        if (($item = $this->catalogue->named($document->name)) && 'theme' !== $item->getKind()) throw new CatalogueException(409, 'That name belongs to a non-theme catalogue item.');
        if ($this->entityManager->getRepository(Theme::class)->findOneBy(['name' => $document->name])) { throw new CatalogueException(409, 'Theme name already exists.'); }
        $theme = new Theme($document->name, $document);
        $this->entityManager->persist($theme);
        $this->families->attach($theme, $family);
        try { $this->entityManager->flush(); }
        catch (UniqueConstraintViolationException) { throw new CatalogueException(409, 'Theme name already exists.'); }

        return $theme;
    }

    public function save(Theme $theme, int $revision, \stdClass $document, bool $managedImport = false): Theme
    {
        if ($this->families->forTheme($theme)->isBuiltIn()) throw new CatalogueException(409, 'Duplicate the built-in family to customize it.');
        if (($theme->getDocument()->provenance->managed ?? false) && !$managedImport) throw new CatalogueException(409, 'This is a managed website preset. Use Duplicate/Edit to create your own theme.');
        $this->validator->validate($document);
        $this->fontAssets->files($document);
        if ($theme->getName() !== $document->name) { throw new CatalogueException(422, 'Theme name is immutable.'); }
        if ($theme->getDocument()->colorScheme !== $document->colorScheme) throw new CatalogueException(422, 'Variant mode is fixed. Create the other variant in this family instead.');
        try {
            $this->entityManager->wrapInTransaction(function () use ($theme, $revision, $document): void {
                $this->entityManager->lock($theme, LockMode::OPTIMISTIC, $revision);
                $theme->replace($document);
            });
        } catch (OptimisticLockException) { throw new CatalogueException(412, 'Theme changed since it was opened. Reload and merge your edits.', [], 'revision_mismatch'); }

        return $theme;
    }

    public function publish(Theme $theme, int $revision): ThemeRelease
    {
        if ($revision !== $theme->getRevision()) { throw new CatalogueException(412, 'Theme revision changed.'); }
        $document = $theme->getDocument();
        $this->validator->validate($document);
        if ($this->entityManager->getRepository(ThemeRelease::class)->findOneBy(['theme' => $theme, 'version' => $document->version])) { throw new CatalogueException(409, 'Theme version is immutable and already exists.'); }
        $files = $this->outputs($document);
        $release = new ThemeRelease($theme, $document->version, $document, $files);
        try {
            $this->entityManager->wrapInTransaction(function () use ($theme, $revision, $release): void {
                $this->entityManager->refresh($theme);
                $this->entityManager->lock($theme, LockMode::OPTIMISTIC, $revision);
                $this->entityManager->persist($release);
            });
        } catch (OptimisticLockException) { throw new CatalogueException(412, 'Theme revision changed during validation.'); }
        catch (UniqueConstraintViolationException) { throw new CatalogueException(409, 'Theme version already exists.'); }

        return $release;
    }

    public function outputs(\stdClass $document, ?array $providedFonts = null): array
    {
        $files = $this->css->files($document);
        $build = $this->worker->build(['sources' => (object) ['templates/catalogue/studio/theme-sample/component.html.twig' => ThemeSample::TWIG], 'controllers' => [], 'safelist' => ['text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'font-sans', 'font-heading'], 'theme' => $document]);
        if (!$build['ok']) { throw new CatalogueException(422, 'Theme compilation failed: '.$build['error']['message']); }
        $files['preview.css'] = $build['css'];
        $files = [...$files, ...($providedFonts ?? $this->fontAssets->files($document))];

        return $files;
    }
}
