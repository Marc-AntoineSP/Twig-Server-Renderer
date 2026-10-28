<?php

namespace App\Favorites;

use App\Entity\{CatalogueItem, Theme, User};
use App\Mcp\Security\ComposeIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

/** The studio catalogue is shared by all readers; MCP also applies its scoped voter. */
final readonly class Favorites
{
    public function __construct(private EntityManagerInterface $em, private Security $security, private RequestStack $requests) {}

    public function owner(): User
    {
        $principal = $this->security->getUser();
        $owner = $principal instanceof ComposeIdentity ? $principal->owner : $principal;
        if (!$owner instanceof User || !$owner->isEnabled() || !$this->security->isGranted('ROLE_READER')) {
            throw new CatalogueException(403, 'Sign in to save personal favorites.');
        }
        return $owner;
    }

    /** One query per request, including every card/picker; no per-item lookups. */
    public function states(): array
    {
        $owner = $this->owner();
        $request = $this->requests->getCurrentRequest();
        $key = '_personal_favorites_'.$owner->getId();
        if ($request?->attributes->has($key)) return $request->attributes->get($key);
        $states = ['theme' => [], 'catalogue' => []];
        $rows = $this->em->getConnection()->fetchAllAssociative("SELECT f.theme_id, f.catalogue_item_id FROM personal_favorite f LEFT JOIN theme t ON t.id=f.theme_id LEFT JOIN catalogue_item i ON i.id=f.catalogue_item_id WHERE f.owner_id=:owner AND (t.id IS NOT NULL OR i.kind IN ('component','block','layout'))", ['owner' => $owner->getId()]);
        foreach ($rows as $row) {
            if ($row['theme_id']) $states['theme'][$row['theme_id']] = true;
            else $states['catalogue'][$row['catalogue_item_id']] = true;
        }
        $request?->attributes->set($key, $states);
        return $states;
    }

    public function contains(Theme|CatalogueItem $item): bool
    {
        return isset($this->states()[$item instanceof Theme ? 'theme' : 'catalogue'][$item->getId()]);
    }

    public function hasCatalogueFavorites(): bool
    {
        if ($this->states()['catalogue']) return true;
        return (bool) $this->em->getConnection()->fetchOne("SELECT 1 FROM personal_favorite f JOIN theme t ON t.id=f.theme_id JOIN catalogue_item i ON i.kind='theme' AND t.name=i.namespace || '/' || i.slug WHERE f.owner_id=? LIMIT 1", [$this->owner()->getId()]);
    }

    public function target(string $kind, string $id): Theme|CatalogueItem
    {
        $this->owner();
        $class = match ($kind) {'theme' => Theme::class, 'catalogue' => CatalogueItem::class, default => null};
        $target = $class ? $this->em->find($class, $id) : null;
        if (!$target || ($target instanceof CatalogueItem && !in_array($target->getKind(), ['component','block','layout'], true))) {
            throw new CatalogueException(404, 'Favorite item not found.');
        }
        return $target;
    }

    public function set(string $kind, string $id, bool $favorite): bool
    {
        $target = $this->target($kind, $id);
        $owner = $this->owner();
        $column = $target instanceof Theme ? 'theme_id' : 'catalogue_item_id';
        $table = $target instanceof Theme ? 'theme' : 'catalogue_item';
        $db = $this->em->getConnection();
        $result = $db->transactional(function () use ($db, $owner, $id, $favorite, $column, $table): bool {
            // Serialize deletion with this request; simultaneous repeated PUTs use the unique key.
            if (!$db->fetchOne('SELECT id FROM '.$table.' WHERE id=:id FOR KEY SHARE', ['id' => $id])) throw new CatalogueException(404, 'Favorite item not found.');
            $params = ['owner' => $owner->getId(), 'target' => $id];
            if ($favorite) $db->executeStatement('INSERT INTO personal_favorite (id, owner_id, '.$column.') VALUES (:id, :owner, :target) ON CONFLICT (owner_id, '.$column.') DO NOTHING', [...$params, 'id' => (string) new Ulid()]);
            else $db->executeStatement('DELETE FROM personal_favorite WHERE owner_id=:owner AND '.$column.'=:target', $params);
            return (bool) $db->fetchOne('SELECT 1 FROM personal_favorite WHERE owner_id=:owner AND '.$column.'=:target', $params);
        });
        $this->requests->getCurrentRequest()?->attributes->remove('_personal_favorites_'.$owner->getId());
        return $result;
    }
}
