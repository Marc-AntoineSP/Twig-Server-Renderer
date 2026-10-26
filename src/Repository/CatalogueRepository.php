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

    public function search(SearchQuery $query): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $where = ['1 = 1'];
        $params = [];
        if ('' !== trim($query->q)) {
            $where[] = "(LOWER(namespace || '/' || slug) LIKE :search OR LOWER(title) LIKE :search OR LOWER(description) LIKE :search OR LOWER(tags::text) LIKE :search)";
            $params['search'] = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($query->q))).'%';
        }
        if ('' !== $query->kind) {
            $where[] = 'kind = :kind';
            $params['kind'] = $query->kind;
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

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'query' => $query];
    }
}
