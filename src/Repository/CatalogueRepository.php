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
            $where[] = "EXISTS (SELECT 1 FROM personal_favorite f LEFT JOIN theme t ON t.id=f.theme_id WHERE f.owner_id=:favorite_owner AND (f.catalogue_item_id=catalogue_item.id OR (catalogue_item.kind='theme' AND t.name=catalogue_item.namespace || '/' || catalogue_item.slug)))";
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
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->find($id);
        }

        $summaries=[];if($ids){foreach($connection->fetchAllAssociative("SELECT i.id,(SELECT r.version FROM catalogue_release r WHERE r.item_id=i.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS release_version,(SELECT r.archive_hash FROM catalogue_release r WHERE r.item_id=i.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS archive_hash,COALESCE(jsonb_array_length(d.manifest_data->'dependencies'),0) AS dependency_count,d.revision FROM catalogue_item i JOIN draft d ON d.item_id=i.id WHERE i.id IN (?)",[$ids],[\Doctrine\DBAL\ArrayParameterType::STRING]) as $row)$summaries[$row['id']]=$row;}
        $themeTargets = [];
        $themeNames = array_map(static fn ($item) => $item->getName(), array_filter($items, static fn ($item) => $item->getKind() === 'theme'));
        if ($themeNames) foreach ($this->getEntityManager()->getRepository(\App\Entity\Theme::class)->findBy(['name' => $themeNames]) as $theme) $themeTargets[$theme->getName()] = $theme;
        return ['themeTargets'=>$themeTargets,'summaries'=>$summaries,'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'query' => $query];
    }
}
