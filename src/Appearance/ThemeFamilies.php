<?php

namespace App\Appearance;

use App\Entity\Theme;
use App\Entity\ThemeFamily;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\ThemeValidator;
use Symfony\Component\Uid\Ulid;

final readonly class ThemeFamilies
{
    public function __construct(private EntityManagerInterface $em, private ThemeValidator $validator) {}

    public function default(): ThemeFamily
    {
        if ($family = $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => 'studio/default'])) return $family;
        return $this->em->wrapInTransaction(function (): ThemeFamily {
            $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(51010401)');
            if ($existing = $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => 'studio/default'])) return $existing;
            $family = new ThemeFamily('studio/default', 'Studio default', true);
            $this->em->persist($family);
            foreach (['light', 'dark'] as $mode) {
                $name = 'studio/application-default-'.$mode;
                if ($this->em->getRepository(Theme::class)->findOneBy(['name' => $name])) $name .= '-'.strtolower(substr((string) new Ulid(), -8));
                $document = DefaultThemes::document($mode, $name); $this->validator->validate($document);
                $theme = new Theme($name, $document); $this->em->persist($theme); $family->attach($theme);
            }
            return $family;
        });
    }

    public function findForTheme(Theme $theme): ?ThemeFamily
    {
        return $this->em->getRepository(ThemeFamily::class)->createQueryBuilder('f')->where('f.lightTheme = :theme OR f.darkTheme = :theme')->setParameter('theme', $theme)->getQuery()->getOneOrNullResult();
    }

    public function forTheme(Theme $theme): ThemeFamily
    {
        if ($family = $this->findForTheme($theme)) return $family;
        $family = $this->attach($theme); $this->em->flush(); return $family;
    }

    public function attach(Theme $theme, ?ThemeFamily $family = null): ThemeFamily
    {
        if (!$family) {
            $doc = $theme->getDocument(); $provenance = $doc->provenance ?? null;
            $known = ($provenance->managed ?? false) && ($provenance->collection ?? '') === 'website-inspired' && isset($provenance->reference);
            $key = $known ? 'websites/ref-'.substr(hash('sha256', $provenance->reference), 0, 24) : $theme->getName();
            $family = $known ? $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => $key]) : null;
            $family ??= new ThemeFamily($key, $known ? $provenance->reference.'-inspired' : $theme->getName());
            $this->em->persist($family);
        }
        try { $family->attach($theme); }
        catch (\InvalidArgumentException $error) { throw new CatalogueException(409, $error->getMessage()); }
        return $family;
    }

    public function choices(): array
    {
        $default = $this->default(); $rows = [];
        foreach ($this->em->getRepository(ThemeFamily::class)->findBy([], ['label' => 'ASC']) as $family) {
            if (!$family->variants()) continue;
            $rows[] = ['id' => $family->getId(), 'label' => $family->getLabel(), 'modes' => array_keys($family->variants()), 'default' => $family->getId() === $default->getId()];
        }
        return $rows;
    }

    public function describe(ThemeFamily $family): array
    {
        $variants = [];
        foreach ($family->variants() as $mode => $theme) $variants[$mode] = ['id' => $theme->getId(), 'revision' => $theme->getRevision(), 'name' => $theme->getName(), 'readOnly' => $family->isBuiltIn() || ($theme->getDocument()->provenance->managed ?? false)];
        return ['id' => $family->getId(), 'key' => $family->getKey(), 'label' => $family->getLabel(), 'revision' => $family->getRevision(), 'builtIn' => $family->isBuiltIn(), 'variants' => $variants];
    }
}
