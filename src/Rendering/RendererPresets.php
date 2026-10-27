<?php

namespace App\Rendering;

use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use Styleguide\Contracts\PropsValidator;
use App\Entity\CatalogueItem;
use App\Entity\RendererPreset;
use App\Entity\User;
use App\Entity\ThemeRelease;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RendererPresets
{
    public function __construct(private EntityManagerInterface $entityManager, private CatalogueWriter $catalogue, private PropsValidator $props, private ValidatorInterface $validator, private \App\Font\FontLibrary $fonts)
    {
    }

    public function read(CatalogueItem $item, User $owner): \stdClass
    {
        $stored = $this->entityManager->getRepository(RendererPreset::class)->findOneBy(['item' => $item, 'owner' => $owner]);

        return $stored ? (object) [...(new RendererSettings())->toArray(),...(array)$stored->getSettings()] : (object) [...(new RendererSettings())->toArray(), 'props' => $this->catalogue->draft($item)->getManifestData()->defaults];
    }

    public function save(CatalogueItem $item, User $owner, RendererSettings $settings): \stdClass
    {
        if (count($this->validator->validate($settings))) {
            throw new CatalogueException(422, 'Renderer settings exceed their supported bounds.');
        }
        foreach ([$settings->bodyFont, $settings->headingFont, $settings->codeFont] as $font) if ('theme' !== $font) $this->fonts->definition($font);
        if ($settings->themeReleaseId && !$this->entityManager->find(ThemeRelease::class, $settings->themeReleaseId)) {
            throw new CatalogueException(422, 'Choose an existing immutable theme release.');
        }
        $draft = $this->catalogue->draft($item);
        $props = $settings->props ?? $draft->getManifestData()->defaults;
        if (!$props instanceof \stdClass) {
            throw new CatalogueException(422, 'Renderer props must be an object.');
        }
        $schema = json_decode($draft->getSourceMap()->{$draft->getManifestData()->propsSchema}, false, 24, JSON_THROW_ON_ERROR);
        $this->props->validateValue($props, $schema);
        $stored = $this->entityManager->getRepository(RendererPreset::class)->findOneBy(['item' => $item, 'owner' => $owner]);
        $settings = new RendererSettings(...[...$settings->toArray(), 'props' => $props]);
        if ($stored) {
            $stored->replace($settings);
        } else {
            $stored = new RendererPreset($owner, $item, $settings);
            $this->entityManager->persist($stored);
        }
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new CatalogueException(409, 'Renderer settings changed concurrently. Reload them before retrying.');
        }

        return $stored->getSettings();
    }
}
