<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/** Bounded scalar summaries: never hydrate source maps, theme documents or all favorites. */
final readonly class WorkspaceRepository
{
    public function __construct(private Connection $db) {}

    public function overview(User $owner): array
    {
        return ['recent' => $this->summaries($owner, false), 'favorites' => $this->summaries($owner, true)];
    }

    private function summaries(User $owner, bool $favoritesOnly): array
    {
        // All enabled studio readers share drafts, including another editor's work.
        // updated_by is attribution, never an ownership predicate. Filter personal
        // favorites before LIMIT; themes retain their typed variant ID.
        $favorite = "EXISTS (SELECT 1 FROM personal_favorite f WHERE f.owner_id=:owner
            AND ((e.kind='theme' AND f.theme_id=e.id) OR (e.kind<>'theme' AND f.catalogue_item_id=e.id)))";
        $filter = $favoritesOnly ? $favorite : 'NOT e.built_in';

        return $this->db->fetchAllAssociative(<<<SQL
            WITH entries AS (
                SELECT i.id, i.namespace || '/' || i.slug AS name, i.title, i.kind,
                    d.revision, GREATEST(i.updated_at, d.updated_at) AS updated_at,
                    jsonb_exists(d.source_map, 'meta/composition.json') AS composition,
                    true AS editable, false AS built_in, NULL::text AS color_scheme
                FROM catalogue_item i JOIN draft d ON d.item_id=i.id
                WHERE i.kind IN ('component', 'block', 'layout')
                UNION ALL
                SELECT t.id, t.name, COALESCE(g.label, t.document->'provenance'->>'title', t.name), 'theme',
                    t.revision, t.updated_at, false,
                    NOT (COALESCE(g.built_in, false) OR COALESCE((t.document->'provenance'->>'managed')::boolean, false)),
                    COALESCE(g.built_in, false), t.document->>'colorScheme'
                FROM theme t LEFT JOIN theme_family g ON g.light_theme_id=t.id OR g.dark_theme_id=t.id
            ), selected AS (
                SELECT e.* FROM entries e WHERE $filter
                ORDER BY e.updated_at DESC, e.id DESC LIMIT 6
            )
            SELECT e.*, $favorite AS favorite,
                CASE WHEN e.kind='theme' THEN
                    (SELECT r.version FROM theme_release r WHERE r.theme_id=e.id ORDER BY r.created_at DESC, r.id DESC LIMIT 1)
                ELSE
                    (SELECT r.version FROM catalogue_release r WHERE r.item_id=e.id ORDER BY r.created_at DESC, r.id DESC LIMIT 1)
                END AS release_version
            FROM selected e ORDER BY e.updated_at DESC, e.id DESC
            SQL, ['owner' => $owner->getId()]);
    }
}
