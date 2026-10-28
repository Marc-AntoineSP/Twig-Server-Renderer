<?php

namespace App\Repository;

use App\Entity\Theme;
use App\Entity\ThemeRelease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ThemeRelease> */
final class ThemeReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThemeRelease::class);
    }

    public function latest(Theme $theme): ?ThemeRelease
    {
        return $this->findOneBy(['theme' => $theme], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** One immutable release per visible gallery card, without per-card queries. */
    public function latestForThemes(array $themes): array
    {
        if (!$themes) return [];
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT ON (theme_id) id FROM theme_release WHERE theme_id IN (?) ORDER BY theme_id, created_at DESC, id DESC',
            [array_map(static fn (Theme $theme) => $theme->getId(), $themes)], [ArrayParameterType::STRING],
        );
        $releases = [];
        foreach ($ids ? $this->findBy(['id' => $ids]) : [] as $release) $releases[$release->getTheme()->getId()] = $release;

        return $releases;
    }
}
