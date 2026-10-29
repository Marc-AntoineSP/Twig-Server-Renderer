<?php

namespace App\Appearance;

use App\Entity\Theme;
use App\Entity\ThemeFamily;
use App\Theme\ThemeWriter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\Uid\Ulid;

final readonly class ThemeFamilyWriter
{
    public function __construct(private EntityManagerInterface $em, private ThemeWriter $writer, private ThemeFamilies $families) {}

    public function create(\stdClass $document, string $variants = 'single', ?string $label = null): Theme
    {
        if (!in_array($variants, ['single', 'pair'], true)) throw new CatalogueException(422, 'Choose a single variant or the complete light/dark starting pair.');
        $label = $this->label(null === $label || '' === trim($label) ? $document->name : $label);
        return $this->em->wrapInTransaction(function () use ($document, $variants, $label): Theme {
            $theme = $this->writer->create($document); $family = $this->families->ensureForTheme($theme); $family->rename($label);
            if ('pair' === $variants) $this->otherVariant($theme, $family);
            return $theme;
        });
    }

    public function duplicate(Theme $theme): Theme
    {
        $original = $this->families->ensureForTheme($theme);
        return $this->em->wrapInTransaction(function () use ($theme, $original): Theme {
            $suffix = strtolower(substr((string) new Ulid(), -10));
            $family = null; $selected = null;
            foreach ($original->variants() as $mode => $source) {
                $document = $source->getDocument();
                $slug = 'copy-'.substr(explode('/', $source->getName())[1], 0, 40).'-'.$suffix.'-'.$mode;
                $document->name = 'studio/'.$slug; $document->cssName = $slug; $document->version = '1.0.0'; $document->default = false; $document->prefersDark = false;
                if (isset($document->provenance)) $document->provenance->managed = false;
                $copy = $this->writer->create($document, $family); $family ??= $this->families->ensureForTheme($copy);
                if ($source->getId() === $theme->getId()) $selected = $copy;
            }
            $family->rename(mb_substr($original->getLabel().' (copy)', 0, 120));
            return $selected;
        });
    }

    public function addVariant(Theme $theme): Theme
    {
        $family = $this->families->ensureForTheme($theme);
        if ($family->isBuiltIn() || ($theme->getDocument()->provenance->managed ?? false)) throw new CatalogueException(409, 'Duplicate this read-only family before adding a variant.');
        return $this->em->wrapInTransaction(function () use ($theme, $family): Theme {
            $this->em->lock($family, LockMode::PESSIMISTIC_WRITE); $this->em->refresh($family);
            return $this->otherVariant($theme, $family);
        });
    }

    public function rename(ThemeFamily $family, ?string $label, ?int $revision = null): void
    {
        if (null === $label || '' === trim($label) || $label === $family->getLabel()) return;
        if ($family->isBuiltIn()) throw new CatalogueException(409, 'Duplicate the built-in family to customize it.');
        if (null !== $revision && $revision !== $family->getRevision()) throw new CatalogueException(412, 'The family name changed in another editor. Reload before renaming it.');
        $family->rename($this->label($label));
    }

    private function otherVariant(Theme $source, ThemeFamily $family): Theme
    {
        $mode = 'light' === $source->getDocument()->colorScheme ? 'dark' : 'light';
        if (isset($family->variants()[$mode])) throw new CatalogueException(409, 'This family already has a '.$mode.' variant.');
        $name = mb_substr($source->getName(), 0, 80).'-'.$mode;
        if ($this->em->getRepository(Theme::class)->findOneBy(['name' => $name])) $name .= '-'.strtolower(substr((string) new Ulid(), -8));
        $document = DefaultThemes::document($mode, $name);
        $original = $source->getDocument();
        $document->typography = $original->typography; $document->fonts = $original->fonts; $document->geometry = $original->geometry;
        return $this->writer->create($document, $family);
    }
    private function label(string $label): string
    {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > 120 || preg_match('/[\x00-\x1f\x7f]/', $label)) throw new CatalogueException(422, 'Use a family display name of 1–120 characters without control characters.');
        return $label;
    }
}
