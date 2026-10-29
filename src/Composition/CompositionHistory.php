<?php

namespace App\Composition;

use App\Entity\{CatalogueItem, CompositionRevision, Draft, User};
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CanonicalJson, CatalogueException};

/** Authorized saved snapshots shared by the browser and MCP; never an undo store. */
final readonly class CompositionHistory
{
    public function __construct(private CompositionWorkspace $workspace, private CompositionTree $trees, private EntityManagerInterface $em, private CanonicalJson $json) {}

    public function page(CatalogueItem $item, int $offset = 0, int $limit = 20): array
    {
        if ($offset < 0 || $offset > 10000 || $limit < 1 || $limit > 50) throw new CatalogueException(422, 'History pages allow offset 0–10000 and limit 1–50.');
        $draft = $this->workspace->require($item);
        $this->workspace->prepare($item, $draft->getRevision(), $this->workspace->document($draft));
        $records = $this->em->getRepository(CompositionRevision::class)->findBy(['item' => $item], ['revision' => 'DESC'], $limit + 1, $offset);
        $rows = [];
        foreach (array_slice($records, 0, $limit) as $i => $record) {
            $previous = $records[$i + 1] ?? null;
            $counts = $previous ? $this->difference($item, $previous->getDocument(), $record->getDocument())['counts'] : null;
            if ($counts !== null) $counts['metadata'] = (int) ($previous->getTitle() !== $record->getTitle()) + (int) ($previous->getDescription() !== $record->getDescription());
            $rows[] = $record->describe() + ['comparedWith' => $previous?->getRevision(), 'changes' => $counts];
        }
        return ['history' => $rows, 'revision' => $draft->getRevision(), 'nextOffset' => count($records) > $limit ? $offset + $limit : null, 'warnings' => ['History contains only captured saved revisions. Earlier uncaptured or unavailable revisions cannot be reconstructed.']];
    }

    public function inspect(CatalogueItem $item, int $revision, int $expected): array
    {
        $draft = $this->workspace->require($item); $this->workspace->expected($draft, $expected);
        $snapshot = $this->snapshot($item, $revision);
        // Revalidate historical references under today's permissions before exposing their differences.
        $this->workspace->prepare($item, $expected, $snapshot->getDocument());
        $diff = $this->difference($item, $this->workspace->document($draft), $snapshot->getDocument());
        $metadata = [];
        foreach (['title' => [$item->getTitle(), $snapshot->getTitle()], 'description' => [$item->getDescription(), $snapshot->getDescription()]] as $field => [$before, $after]) {
            if ($before !== $after) $metadata[] = ['field' => $field, 'before' => mb_substr($before, 0, 240), 'after' => mb_substr($after, 0, 240)];
        }
        $diff['counts']['metadata'] = count($metadata);
        return $snapshot->describe() + $diff + ['expectedRevision' => $expected, 'metadata' => $metadata, 'title' => $snapshot->getTitle()];
    }

    public function restore(CatalogueItem $item, int $expected, int $revision, User $actor): Draft
    {
        $this->workspace->require($item, 'COMPOSE_EDIT');
        $snapshot = $this->snapshot($item, $revision);
        return $this->workspace->save($item, $expected, $snapshot->getDocument(), $actor, $snapshot->getTitle(), $snapshot->getDescription(), 'Restored revision '.$revision);
    }

    private function snapshot(CatalogueItem $item, int $revision): CompositionRevision
    {
        return $this->em->getRepository(CompositionRevision::class)->findOneBy(['item' => $item, 'revision' => $revision]) ?? throw new CatalogueException(404, 'Recoverable revision not found. It was never captured or is no longer available.', [], 'NOT_FOUND');
    }

    private function difference(CatalogueItem $item, \stdClass $before, \stdClass $after): array
    {
        $a = $this->trees->index($this->trees->normalize($before, $item->getId()));
        $b = $this->trees->index($this->trees->normalize($after, $item->getId()));
        $counts = array_fill_keys(['inserted', 'removed', 'moved', 'properties', 'references'], 0); $changes = [];
        $positions = static function (array $index, array $other): array {
            $ranks = []; $parents = [];
            foreach ($index as $id => $entry) if (isset($other[$id]) && $entry['parentId'] === $other[$id]['parentId']) {
                $parent = $entry['parentId'] ?? 'root'; $ranks[$id] = $parents[$parent] ?? 0; $parents[$parent] = $ranks[$id] + 1;
            }
            return $ranks;
        };
        $oldRanks = $positions($a, $b); $newRanks = $positions($b, $a);
        $add = static function (string $kind, string $id, array $data = []) use (&$counts, &$changes): void {
            ++$counts[$kind]; if (count($changes) < 100) $changes[] = ['kind' => $kind, 'nodeId' => $id] + $data;
        };
        foreach ($a as $id => $entry) if (!isset($b[$id])) $add('removed', $id, ['label' => $this->label($entry['node'])]);
        foreach ($b as $id => $entry) {
            $node = $entry['node'];
            if (!isset($a[$id])) {$add('inserted', $id, ['label' => $this->label($node)]); continue;}
            $old = $a[$id]; $oldNode = $old['node'];
            if ($old['parentId'] !== $entry['parentId'] || ($oldRanks[$id] ?? null) !== ($newRanks[$id] ?? null)) $add('moved', $id, ['label' => $this->label($node), 'before' => ($old['parentId'] ?? 'root').':'.$old['position'], 'after' => ($entry['parentId'] ?? 'root').':'.$entry['position']]);
            foreach (['type', 'tag', 'classes', 'value', 'name', 'version'] as $field) {
                if ($this->json->encode($oldNode->$field ?? null) !== $this->json->encode($node->$field ?? null)) $add(in_array($field, ['name', 'version'], true) ? 'references' : 'properties', $id, ['label' => $this->label($node), 'field' => $field, 'before' => $this->short($oldNode->$field ?? null), 'after' => $this->short($node->$field ?? null)]);
            }
            $oldProps = (array) ($oldNode->props ?? new \stdClass()); $props = (array) ($node->props ?? new \stdClass());
            foreach (array_unique([...array_keys($oldProps), ...array_keys($props)]) as $key) {
                if (array_key_exists($key, $oldProps) !== array_key_exists($key, $props) || $this->json->encode($oldProps[$key] ?? null) !== $this->json->encode($props[$key] ?? null)) $add('properties', $id, ['label' => $this->label($node), 'field' => 'props.'.$key, 'before' => $this->short($oldProps[$key] ?? null), 'after' => $this->short($props[$key] ?? null)]);
            }
        }
        return ['counts' => $counts, 'differences' => $changes, 'truncated' => array_sum($counts) > count($changes)];
    }
    private function label(object $node): string {return mb_substr(match ($node->type) {'text' => 'Text: '.$node->value, 'reference' => $node->name.'@'.$node->version, default => '<'.$node->tag.'>'}, 0, 120);}
    private function short(mixed $value): string {return mb_strimwidth($this->json->encode($value), 0, 240, '…');}
}
