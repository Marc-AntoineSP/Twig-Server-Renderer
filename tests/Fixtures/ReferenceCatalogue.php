<?php

namespace App\Tests\Fixtures;

use App\Catalogue\{CreateItemInput, SourceFactory};
use App\Entity\{CatalogueItem, Draft, Favorite, Release, User};
use App\Packaging\ArchiveStore;
use App\Testing\BrowserRunGuard;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CanonicalJson, CompositionValidator, PackageValidator, PropsValidator, ThemeCssGenerator, ThemeValidator};

/** Validated, archive-backed load data. It does not pretend to exercise publication workers. */
final class ReferenceCatalogue
{
    public static function create(EntityManagerInterface $em, User $owner, string $prefix, int $favorites = 80, int $versions = 250): array
    {
        $database = $em->getConnection()->fetchOne('SELECT current_database()');
        if (!str_ends_with($database, '_test')) (new BrowserRunGuard($em->getConnection()))->verify();
        $json = new CanonicalJson(); $themes = new ThemeValidator();
        $validator = new PackageValidator(new PropsValidator(), $themes, new ThemeCssGenerator($themes, $json), new CompositionValidator());
        $archives = new ArchiveStore($json, '/app/var/catalogue');
        $factory = new SourceFactory();
        $make = function (string $slug, bool $favorite, int $count) use ($em, $owner, $prefix, $validator, $archives, $factory): array {
            $item = new CatalogueItem($prefix, $slug, 'component', 'Duplicate reference title', 'A representative archive-backed reference.');
            [$manifest, $sources] = $factory->initial(new CreateItemInput($item->getName(), 'component', $item->getTitle()));
            $manifest = $validator->validate($manifest, $sources, true);
            $em->persist($item); $em->persist(new Draft($item, $manifest, $sources, $owner));
            if ($favorite) $em->persist(new Favorite($owner, $item));
            $releases = [];
            for ($index = 0; $index < $count; $index++) {
                $m = clone $manifest; $m->version = '1.0.'.$index;
                [$key, $hash] = $archives->put($m, $sources);
                $release = new Release($item, $m->version, $m, $hash, $key);
                $em->persist($release); $releases[] = ['id' => $release->getId(), 'version' => $release->getVersion()];
            }
            $em->flush();
            return ['id' => $item->getId(), 'name' => $item->getName(), 'releases' => $releases];
        };
        $old = $make('aaa-old-nonfavorite', false, 1);
        $manyVersions = $make('many-versions', true, $versions);
        for ($index = 0; $index < $favorites; $index++) $make(sprintf('favorite-%03d', $index), true, 3);
        return ['old' => $old, 'manyVersions' => $manyVersions, 'favoriteItems' => $favorites + 1, 'releaseCount' => 1 + $versions + 3 * $favorites];
    }
}
