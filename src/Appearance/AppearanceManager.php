<?php

namespace App\Appearance;

use App\Entity\AppearancePreference;
use App\Entity\AppearanceSnapshot;
use App\Entity\Theme;
use App\Entity\ThemeFamily;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Styleguide\Contracts\CatalogueException;

final readonly class AppearanceManager
{
    public function __construct(private EntityManagerInterface $em, private ThemeFamilies $families, private AppearanceSnapshots $snapshots) {}

    public function read(?User $user, string $guestMode = 'system'): array
    {
        $preference = $user ? $this->em->find(AppearancePreference::class, $user->getId()) : null;
        if (!$preference) return $this->familyState($this->families->default(), $user ? 'system' : $guestMode, $user);
        $family = $preference->getFamily(); $snapshots = $preference->snapshots();
        $valid = $family && $snapshots;
        foreach ($snapshots as $snapshot) $valid = $valid && $snapshot->getTheme() && 'trial' !== $snapshot->getKind();
        if (!$valid) {
            $state = $this->familyState($this->families->default(), 'system', $user);
            $state['revision'] = $preference->getRevision();
            $state = $this->persist($state, $user, $preference->getRevision());
            $state['notice'] = 'Your saved theme is unavailable. Studio default appearance has been restored.';
            return $state;
        }
        return $this->state($family, $snapshots, $preference->getMode(), $user, $preference->getRevision());
    }

    public function prepare(array $input, ?User $user, string $guestMode = 'system'): array
    {
        $current = $this->read($user, $guestMode); $kind = $input['kind'] ?? 'family';
        $mode = $input['mode'] ?? $current['mode'];
        if (!in_array($mode, ['light', 'dark', 'system'], true)) throw new CatalogueException(422, 'Choose light, dark or system appearance.');
        if ('mode' === $kind) return [...$current, 'mode' => $mode];
        if ('default' === $kind) return $this->familyState($this->families->default(), 'system', $user, $current['revision']);
        if (!$user && 'family' === $kind && ($input['familyId'] ?? '') === $this->families->default()->getId()) return $this->familyState($this->families->default(), $mode, null);
        if (!$user) throw new CatalogueException(403, 'Sign in to apply a catalogue theme.');
        if ('restore' === $kind) {
            $family = $this->findFamily($input['familyId'] ?? null); $selected = [];
            foreach (['light', 'dark'] as $variantMode) if (isset($input['snapshots'][$variantMode])) {
                $id = $input['snapshots'][$variantMode]; $snapshot = is_string($id) && preg_match('/^[a-f0-9]{64}$/D', $id) ? $this->em->find(AppearanceSnapshot::class, $id) : null;
                if (!$snapshot || 'trial' === $snapshot->getKind() || !$snapshot->getTheme() || $snapshot->getDocument()->colorScheme !== $variantMode || $this->families->forTheme($snapshot->getTheme())->getId() !== $family->getId()) throw new CatalogueException(422, 'That appearance snapshot is unavailable.');
                $selected[$variantMode] = $snapshot;
            }
            if (!$selected) throw new CatalogueException(422, 'An appearance needs an available variant.');
            return $this->state($family, $selected, $mode, $user, $current['revision']);
        }
        if ('family' !== $kind) throw new CatalogueException(422, 'Unknown appearance action.');
        if (isset($input['themeId'])) {
            $theme = $this->findTheme($input['themeId']);
            if (isset($input['themeRevision']) && $theme->getRevision() !== $input['themeRevision']) throw new CatalogueException(412, 'Theme changed. Refresh before applying the saved revision.');
            $family = $this->families->forTheme($theme);
        } else $family = $this->findFamily($input['familyId'] ?? null);
        return $this->familyState($family, $mode, $user, $current['revision']);
    }

    public function persist(array $state, ?User $user, int $expectedRevision): array
    {
        if (!$user) return $state;
        $family = $this->findFamily($state['familyId']); $selected = [];
        foreach ($state['variants'] as $mode => $variant) {
            $snapshot = $this->em->find(AppearanceSnapshot::class, $variant['id']);
            if (!$snapshot || !$snapshot->getTheme() || 'trial' === $snapshot->getKind()) throw new CatalogueException(422, 'The prepared theme is no longer available.');
            $selected[$mode] = $snapshot;
        }
        try {
            $this->em->wrapInTransaction(function () use ($user, $family, $selected, $state, $expectedRevision): void {
                $preference = $this->em->find(AppearancePreference::class, $user->getId());
                if (!$preference) {
                    if (0 !== $expectedRevision) throw new CatalogueException(412, 'Appearance changed. Refresh and retry.');
                    $preference = new AppearancePreference($user); $this->em->persist($preference);
                } else {
                    $this->em->refresh($preference);
                    $this->em->lock($preference, LockMode::OPTIMISTIC, $expectedRevision);
                }
                $preference->select($family, $selected, $state['mode']);
            });
        } catch (OptimisticLockException|UniqueConstraintViolationException) { throw new CatalogueException(412, 'Appearance changed in another request. Retry your selection.'); }
        $preference = $this->em->find(AppearancePreference::class, $user->getId());
        return $this->state($family, $selected, $state['mode'], $user, $preference->getRevision());
    }

    public function trial(\stdClass $document, User $user): array
    {
        $current = $this->read($user); $snapshot = $this->snapshots->trial($document, $user);
        return [...$current, 'familyName' => $document->name.' (unsaved)', 'mode' => $document->colorScheme, 'variants' => [$document->colorScheme => $this->snapshots->describe($snapshot)], 'trial' => true];
    }

    public function findTheme(mixed $id): Theme
    {
        if (!is_string($id) || !\Symfony\Component\Uid\Ulid::isValid($id)) throw new CatalogueException(422, 'Choose a valid theme ID.');
        return $this->em->find(Theme::class, $id) ?? throw new CatalogueException(404, 'Theme is unavailable.');
    }
    public function findFamily(mixed $id): ThemeFamily
    {
        if (!is_string($id) || !\Symfony\Component\Uid\Ulid::isValid($id)) throw new CatalogueException(422, 'Choose a valid family ID.');
        return $this->em->find(ThemeFamily::class, $id) ?? throw new CatalogueException(404, 'Theme family is unavailable.');
    }
    private function familyState(ThemeFamily $family, string $mode, ?User $user, int $revision = 0): array
    {
        $selected = [];
        foreach ($family->variants() as $variantMode => $theme) $selected[$variantMode] = $this->snapshots->saved($theme, $family->isBuiltIn());
        if (!$selected) throw new CatalogueException(404, 'This family has no available variants.');
        return $this->state($family, $selected, $mode, $user, $revision);
    }
    private function state(ThemeFamily $family, array $selected, string $mode, ?User $user, int $revision): array
    {
        $variants = [];
        foreach ($selected as $variantMode => $snapshot) $variants[$variantMode] = $this->snapshots->describe($snapshot);
        return ['familyId' => $family->getId(), 'familyName' => $family->getLabel(), 'mode' => $mode, 'variants' => $variants, 'revision' => $revision, 'scope' => $user?->getId() ?? 'guest', 'default' => $family->isBuiltIn(), 'trial' => false];
    }
}
