<?php

namespace App\Composition;

use App\Entity\{CatalogueItem, User};
use App\Rendering\ReleaseThumbnails;
use Doctrine\DBAL\Connection;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Browser release discovery. MCP's draft/item discovery has a different contract. */
final readonly class ReferenceDiscovery
{
    public const PAGE_SIZE = 20;

    public function __construct(private Connection $db, private CompositionWorkspace $workspace, private CompositionTree $trees, private TokenStorageInterface $tokens, private ValidatorInterface $validator, private ReleaseThumbnails $thumbnails, private UrlGeneratorInterface $urls) {}

    public function search(CatalogueItem $item, ReferenceSearchInput $input): array
    {
        $owner = $this->browserOwner();
        $draft = $this->workspace->require($item);
        $this->workspace->expected($draft, $input->expectedRevision);
        if (count($this->validator->validate($input))) throw new CatalogueException(422, 'Invalid reference search.');
        $document = $this->trees->normalize($input->document, $item->getId());
        $index = $this->trees->index($document);
        $selected = $index[$input->selectedNodeId] ?? throw new CatalogueException(404, 'Selected node is unavailable.');
        if ('replace' === $input->mode && 'reference' !== $selected['node']->type) throw new CatalogueException(422, 'Choose a reference node to replace.');
        // A structural dry run uses the same parent/slot/node/depth rules as mutation.
        // It is never persisted or compiled, and cannot change the supplied document.
        $command = 'insert' === $input->mode
            ? (object) ['type' => 'add_reference', 'selectedId' => $input->selectedNodeId, 'name' => 'reference/selection', 'version' => '1.0.0']
            : (object) ['type' => 'update_node', 'nodeId' => $input->selectedNodeId, 'changes' => (object) ['name' => 'reference/selection', 'version' => '1.0.0']];
        $this->trees->apply($document, $item->getId(), [$command]);
        $references = [];
        foreach ($index as $id => $entry) {
            $node = $entry['node'];
            if ('reference' === $node->type && !('replace' === $input->mode && $id === $input->selectedNodeId)) $references[$node->name.'@'.$node->version] = ['name' => $node->name, 'version' => $node->version];
        }
        $query = $input->query;
        $where = ["i.kind IN ('component','block','layout')", 'i.id <> :item'];
        $params = ['owner' => $owner->getId(), 'item' => $item->getId(), 'name' => $item->getName(), 'references' => json_encode(array_values($references), JSON_THROW_ON_ERROR)];
        if ('' !== trim($query->q)) {
            $where[] = "LOWER(i.namespace || '/' || i.slug || ' ' || COALESCE(r.manifest_data->>'title','') || ' ' || COALESCE(r.manifest_data->>'description','')) LIKE :q";
            $params['q'] = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($query->q))).'%';
        }
        if ($query->kind) { $where[] = 'i.kind=:kind'; $params['kind'] = $query->kind; }
        if ($query->version) { $where[] = 'r.version=:version'; $params['version'] = $query->version; }
        if ($query->favorites) $where[] = 'f.id IS NOT NULL';
        $where = implode(' AND ', $where);
        // UNION deduplicates graph nodes at each depth. Unlike path enumeration this
        // remains bounded on diamonds; reaching depth21 also excludes transitive cycles.
        $sql = <<<SQL
WITH RECURSIVE candidates AS (
    SELECT r.id, r.item_id, r.version, r.archive_hash, r.manifest_data,
           i.namespace || '/' || i.slug AS name, i.kind, (f.id IS NOT NULL) AS favorite
    FROM catalogue_release r JOIN catalogue_item i ON i.id=r.item_id
    LEFT JOIN personal_favorite f ON f.catalogue_item_id=i.id AND f.owner_id=:owner
    WHERE $where
), roots AS (
    SELECT c.id::text AS root_id, c.id AS release_id, c.name, c.version, 0 AS depth FROM candidates c
    UNION
    SELECT '_context', r.id, refs.name, refs.version, 0
    FROM jsonb_to_recordset(CAST(:references AS jsonb)) AS refs(name text, version text)
    LEFT JOIN catalogue_item i ON i.namespace || '/' || i.slug=refs.name
    LEFT JOIN catalogue_release r ON r.item_id=i.id AND r.version=refs.version
), walk AS (
    SELECT * FROM roots
    UNION
    SELECT w.root_id, r.id, dep->>'name', dep->>'version', w.depth+1
    FROM walk w JOIN catalogue_release parent ON parent.id=w.release_id
    CROSS JOIN LATERAL jsonb_array_elements(parent.manifest_data->'dependencies') dep
    LEFT JOIN catalogue_item i ON i.namespace || '/' || i.slug=dep->>'name'
    LEFT JOIN catalogue_release r ON r.item_id=i.id AND r.version=dep->>'version'
    WHERE w.depth<21
), combined AS (
    SELECT root_id,name FROM walk WHERE root_id<>'_context'
    UNION
    SELECT candidate.id::text, context.name FROM candidates candidate CROSS JOIN walk context WHERE context.root_id='_context'
), invalid AS (
    SELECT root_id FROM walk WHERE release_id IS NULL OR depth>=20 OR name=:name
    UNION
    SELECT root_id FROM combined GROUP BY root_id HAVING COUNT(*)>99
    UNION
    SELECT root_id FROM walk GROUP BY root_id,name HAVING COUNT(DISTINCT version)>1
    UNION
    SELECT candidate.root_id FROM walk candidate JOIN walk context
      ON context.root_id='_context' AND candidate.name=context.name AND candidate.version<>context.version
    WHERE candidate.root_id<>'_context'
)
SELECT c.id AS "releaseId", c.item_id AS "itemId", c.name, c.kind, c.version,
       LEFT(c.manifest_data->>'title',120) AS title,
       LEFT(c.manifest_data->>'description',300) AS summary, c.favorite, c.archive_hash
FROM candidates c WHERE NOT EXISTS (SELECT 1 FROM invalid bad WHERE bad.root_id IN (c.id::text,'_context'))
ORDER BY c.name COLLATE "C", split_part(c.version,'.',1)::numeric DESC,
         split_part(c.version,'.',2)::numeric DESC, split_part(c.version,'.',3)::numeric DESC, c.id
LIMIT 21 OFFSET
SQL;
        $sql .= ' '.(self::PAGE_SIZE * ($query->page - 1));
        $rows = $this->db->fetchAllAssociative($sql, $params);
        $more = count($rows) > self::PAGE_SIZE;
        $results = [];
        foreach (array_slice($rows, 0, self::PAGE_SIZE) as $row) {
            $row['favorite'] = (bool) $row['favorite'];
            $row['previewUrl'] = $this->urls->generate('catalogue_show', ['id' => $row['itemId'], 'release' => $row['releaseId']]);
            $row['thumbnailUrl'] = $this->thumbnails->available($row['archive_hash']) ? $this->urls->generate('release_thumbnail', ['id' => $row['releaseId']]) : null;
            unset($row['archive_hash']); $results[] = $row;
        }
        return ['items' => $results, 'page' => $query->page, 'pageSize' => self::PAGE_SIZE, 'hasMore' => $more];
    }

    public function read(CatalogueItem $item, string $name, string $version): array
    {
        $this->browserOwner(); $this->workspace->require($item);
        if (strlen($name)>100 || strlen($version)>32 || !preg_match('~^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$~D', $name) || !preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) throw new CatalogueException(422, 'Choose an exact package name and immutable version.');
        try {
            $release = $this->workspace->release($name, $version);
            $details = $this->workspace->referenceDetails($release);
        } catch (CatalogueException) { throw new CatalogueException(404, 'Reference is unavailable or access changed.'); }
        return $details + ['previewUrl' => $this->urls->generate('catalogue_show', ['id' => $details['itemId'], 'release' => $details['releaseId']])];
    }

    private function browserOwner(): User
    {
        // Session editors share the catalogue. Scoped MCP principals deliberately do
        // not use this browser query; their existing voter-filtered item query remains.
        $principal = $this->tokens->getToken()?->getUser();
        if (!$principal instanceof User || !$principal->isEnabled() || !array_intersect($principal->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN'])) throw new CatalogueException(403, 'A studio editor session is required.');
        return $principal;
    }
}
