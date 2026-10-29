<?php

namespace App\Theme;

use App\Entity\Theme;
use App\Favorites\Favorites;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ThemeCatalogue
{
    public function __construct(private EntityManagerInterface $em, private Favorites $favorites, private \App\Repository\ThemeReleaseRepository $releases, private \App\Appearance\ThemeFamilies $families) {}

    public function search(ThemeSearchQuery $query): array
    {
        $owner = $this->favorites->owner();
        $where = ['1=1']; $params = [];
        if (trim($query->q) !== '') {
            $where[] = "(LOWER(t.name || ' ' || COALESCE(t.document->'provenance'->>'title','') || ' ' || COALESCE(t.document->'provenance'->>'surface','')) LIKE :q OR EXISTS (SELECT 1 FROM theme_family g WHERE (g.light_theme_id=t.id OR g.dark_theme_id=t.id) AND LOWER(g.label) LIKE :q))";
            $params['q'] = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], mb_strtolower(trim($query->q))).'%';
        }
        if ($query->mode !== '') { $where[] = "t.document->>'colorScheme'=:mode"; $params['mode'] = $query->mode; }
        if ($query->favorites) {
            $where[] = 'EXISTS (SELECT 1 FROM personal_favorite f WHERE f.theme_id=t.id AND f.owner_id=:owner)';
            $params['owner'] = $owner->getId();
        }
        $where = implode(' AND ', $where);
        $db = $this->em->getConnection();
        $total = (int) $db->fetchOne('SELECT COUNT(*) FROM theme t WHERE '.$where, $params);
        $pages = max(1, (int) ceil($total / 12)); $page = min($query->page, $pages);
        $order = $query->sort === 'name' ? 't.name ASC, t.id ASC' : 't.updated_at DESC, t.id DESC';
        $ids = $db->fetchFirstColumn('SELECT t.id FROM theme t WHERE '.$where.' ORDER BY '.$order.' LIMIT 12 OFFSET '.(($page - 1) * 12), $params);
        $themes = [];
        if ($ids) {
            foreach ($this->em->getRepository(Theme::class)->findBy(['id' => $ids]) as $theme) $themes[$theme->getId()] = $theme;
        }
        return ['familySummaries' => $this->families->describeMany(array_values($themes)), 'themes' => array_values(array_filter(array_map(fn ($id) => $themes[$id] ?? null, $ids))), 'publishedReleases' => $this->releases->latestForThemes(array_values($themes)), 'total' => $total, 'pages' => $pages, 'page' => $page, 'query' => $query, 'hasFavorites' => (bool) $this->favorites->states()['theme']];
    }
}
