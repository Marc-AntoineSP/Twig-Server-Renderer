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
        return $this->findForTheme($theme) ?? ThemeFamily::legacy($theme);
    }

    /** Explicit creation/import/apply/backfill path; ordinary lookups never repair. */
    public function ensureForTheme(Theme $theme): ThemeFamily
    {
        if ($family = $this->findForTheme($theme)) return $family;
        return $this->em->wrapInTransaction(function () use ($theme): ThemeFamily {
            $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(51010401)');
            return $this->findForTheme($theme) ?? $this->attach($theme);
        });
    }

    public function attach(Theme $theme, ?ThemeFamily $family = null): ThemeFamily
    {
        if (!$family) {
            $doc = $theme->getDocument(); $provenance = $doc->provenance ?? null;
            $known = ($provenance->managed ?? false) && ($provenance->collection ?? '') === 'website-inspired' && isset($provenance->reference);
            $key = $known ? 'websites/ref-'.substr(hash('sha256', $provenance->reference), 0, 24) : $theme->getName();
            $family = $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => $key]);
            // Old imports can contain another variant in the same mode. Keep both
            // themes, as the original migration did, without replacing an attachment.
            if ($family && isset($family->variants()[$doc->colorScheme]) && $family->variants()[$doc->colorScheme]->getId() !== $theme->getId()) {
                $key = 'legacy/theme-'.strtolower($theme->getId()); $family = $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => $key]);
            }
            $family ??= new ThemeFamily($key, mb_substr($known ? $provenance->reference.'-inspired' : $theme->getName(), 0, 120));
            $this->em->persist($family);
        }
        try { $family->attach($theme); }
        catch (\InvalidArgumentException $error) { throw new CatalogueException(409, $error->getMessage()); }
        return $family;
    }

    public function choices(bool $defaultOnly = false): array
    {
        $default = $this->default(); $rows = [];
        $db = $this->em->getConnection();
        $sql = 'SELECT id,label,light_theme_id IS NOT NULL AS light,dark_theme_id IS NOT NULL AS dark FROM theme_family';
        if ($defaultOnly) $sql .= ' WHERE id = ?';
        else $sql .= " UNION ALL SELECT t.id,t.name,(t.document->>'colorScheme'='light'),(t.document->>'colorScheme'='dark') FROM theme t WHERE NOT EXISTS (SELECT 1 FROM theme_family f WHERE f.light_theme_id=t.id OR f.dark_theme_id=t.id)";
        foreach ($db->fetchAllAssociative($sql.' ORDER BY label,id', $defaultOnly ? [$default->getId()] : []) as $family) {
            $modes = array_keys(array_filter(['light' => $family['light'], 'dark' => $family['dark']]));
            if ($modes) $rows[] = ['id' => $family['id'], 'label' => $family['label'], 'modes' => $modes, 'default' => $family['id'] === $default->getId()];
        }
        return $rows;
    }

    /** Bounded page summaries. Only to-one joins, after pagination. */
    public function describeMany(array $themes): array
    {
        if (!$themes) return [];
        $ids = array_map(static fn(Theme $theme) => $theme->getId(), $themes);
        $sql = "SELECT f.id,f.stable_key,f.label,f.revision,f.built_in,l.id AS light_id,l.name AS light_name,l.revision AS light_revision,COALESCE((l.document->'provenance'->>'managed')::boolean,false) AS light_managed,d.id AS dark_id,d.name AS dark_name,d.revision AS dark_revision,COALESCE((d.document->'provenance'->>'managed')::boolean,false) AS dark_managed FROM theme_family f LEFT JOIN theme l ON l.id=f.light_theme_id LEFT JOIN theme d ON d.id=f.dark_theme_id WHERE f.light_theme_id IN (?) OR f.dark_theme_id IN (?)";
        $summaries = [];
        foreach ($this->em->getConnection()->fetchAllAssociative($sql, [$ids,$ids], [\Doctrine\DBAL\ArrayParameterType::STRING,\Doctrine\DBAL\ArrayParameterType::STRING]) as $row) {
            $variants = [];
            foreach (['light','dark'] as $mode) if ($row[$mode.'_id']) $variants[$mode] = ['id' => $row[$mode.'_id'], 'revision' => (int) $row[$mode.'_revision'], 'name' => $row[$mode.'_name'], 'readOnly' => $row['built_in'] || $row[$mode.'_managed']];
            $description = ['id' => $row['id'], 'key' => $row['stable_key'], 'label' => $row['label'], 'revision' => (int) $row['revision'], 'builtIn' => (bool) $row['built_in'], 'variants' => $variants];
            foreach ($variants as $variant) $summaries[$variant['id']] = $description;
        }
        foreach ($themes as $theme) $summaries[$theme->getId()] ??= $this->describe(ThemeFamily::legacy($theme));
        return $summaries;
    }

    public function describe(ThemeFamily $family): array
    {
        $variants = [];
        foreach ($family->variants() as $mode => $theme) $variants[$mode] = ['id' => $theme->getId(), 'revision' => $theme->getRevision(), 'name' => $theme->getName(), 'readOnly' => $family->isBuiltIn() || ($theme->getDocument()->provenance->managed ?? false)];
        return ['id' => $family->getId(), 'key' => $family->getKey(), 'label' => $family->getLabel(), 'revision' => $family->getRevision(), 'builtIn' => $family->isBuiltIn(), 'variants' => $variants];
    }
}
