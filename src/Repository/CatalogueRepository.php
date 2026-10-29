<?php

namespace App\Repository;

use App\Catalogue\SearchQuery;
use App\Entity\CatalogueItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CatalogueItem> */
final class CatalogueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogueItem::class);
    }

    public function named(string $name): ?CatalogueItem
    {
        [$namespace, $slug] = explode('/', $name, 2);

        return $this->findOneBy(['namespace' => $namespace, 'slug' => $slug]);
    }

    public function search(SearchQuery $query, ?\App\Entity\User $owner = null): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $where = ['1 = 1'];
        $params = [];
        if ($query->favorites) {
            if (!$owner) throw new \LogicException('Favorites require an authenticated owner.');
            $where[] = "(EXISTS (SELECT 1 FROM personal_favorite f WHERE f.owner_id=:favorite_owner AND f.catalogue_item_id=catalogue_item.id) OR (catalogue_item.kind='theme' AND EXISTS (SELECT 1 FROM personal_favorite f JOIN theme t ON t.id=f.theme_id WHERE f.owner_id=:favorite_owner AND t.name=catalogue_item.namespace || '/' || catalogue_item.slug)))";
            $params['favorite_owner'] = $owner->getId();
        }
        if ('' !== trim($query->q)) {
            $where[] = "(LOWER(namespace || '/' || slug) LIKE :search OR LOWER(title) LIKE :search OR LOWER(description) LIKE :search OR LOWER(tags::text) LIKE :search)";
            $params['search'] = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($query->q))).'%';
        }
        if ('' !== $query->kind) {
            $where[] = 'kind = :kind';
            $params['kind'] = $query->kind;
        }
        $tags = array_values(array_unique(array_filter(array_map(static fn ($tag) => mb_strtolower(trim($tag)), explode(',', $query->tags)), static fn ($tag) => $tag !== '')));
        if ($tags) {
            $where[] = 'tags @> CAST(:tags AS jsonb)';
            $params['tags'] = json_encode($tags, JSON_THROW_ON_ERROR);
        }
        $where = implode(' AND ', $where);
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM catalogue_item WHERE '.$where, $params);
        $pages = max(1, (int) ceil($total / 12));
        $page = min($query->page, $pages);
        $order = 'name' === $query->sort ? 'namespace ASC, slug ASC' : 'updated_at DESC, id DESC';
        $ids = $connection->fetchFirstColumn('SELECT id FROM catalogue_item WHERE '.$where.' ORDER BY '.$order.' LIMIT 12 OFFSET '.(($page - 1) * 12), $params);
        $byId = [];
        if ($ids) foreach ($this->findBy(['id' => $ids]) as $item) $byId[$item->getId()] = $item;
        // IN does not preserve the page order. A concurrently deleted row is omitted;
        // totals describe the count snapshot, without refilling or shifting the page.
        $items = [];
        foreach ($ids as $id) if (isset($byId[$id])) $items[] = $byId[$id];

        $summaries=[];if($ids){foreach($connection->fetchAllAssociative("SELECT i.id,(SELECT r.id FROM catalogue_release r WHERE r.item_id=i.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS release_id,(SELECT r.version FROM catalogue_release r WHERE r.item_id=i.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS release_version,(SELECT r.archive_hash FROM catalogue_release r WHERE r.item_id=i.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS archive_hash,COALESCE(jsonb_array_length(d.manifest_data->'dependencies'),0) AS dependency_count,d.revision FROM catalogue_item i JOIN draft d ON d.item_id=i.id WHERE i.id IN (?)",[$ids],[\Doctrine\DBAL\ArrayParameterType::STRING]) as $row)$summaries[$row['id']]=$row;}
        $themeTargets = [];
        $themeNames = array_map(static fn ($item) => $item->getName(), array_filter($items, static fn ($item) => $item->getKind() === 'theme'));
        if ($themeNames) foreach ($this->getEntityManager()->getRepository(\App\Entity\Theme::class)->findBy(['name' => $themeNames]) as $theme) $themeTargets[$theme->getName()] = $theme;
        return ['themeTargets'=>$themeTargets,'summaries'=>$summaries,'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'query' => $query];
    }
    /** Enrich only the already authorized MCP page; never hydrate draft source or release manifests.
     * @param list<string> $ids
     */
    public function composerSummaries(array $ids): array
    {
        if (!$ids) return [];
        $db = $this->getEntityManager()->getConnection(); $summaries = [];
        foreach ($db->fetchAllAssociative("SELECT item_id, revision, (source_map->>'meta/composition.json') IS NOT NULL AS composition FROM draft WHERE item_id IN (?)", [$ids], [\Doctrine\DBAL\ArrayParameterType::STRING]) as $row) {
            $summaries[$row['item_id']] = ['revision' => (int) $row['revision'], 'composition' => (bool) $row['composition'], 'releases' => []];
        }
        foreach ($db->fetchAllAssociative('SELECT item_id,id,version FROM (SELECT item_id,id,version,created_at,ROW_NUMBER() OVER (PARTITION BY item_id ORDER BY created_at DESC,id DESC) AS position FROM catalogue_release WHERE item_id IN (?)) ranked WHERE position <= 10 ORDER BY item_id,position', [$ids], [\Doctrine\DBAL\ArrayParameterType::STRING]) as $row) {
            if (isset($summaries[$row['item_id']])) $summaries[$row['item_id']]['releases'][] = ['releaseId' => $row['id'], 'version' => $row['version']];
        }
        return $summaries;
    }

}
