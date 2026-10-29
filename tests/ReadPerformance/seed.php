<?php
// Explicit, deterministic fixtures in a database AND blob store verified as disposable.
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('dev', false); $kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager(); $db = $em->getConnection();
(new App\Testing\BrowserRunGuard($db))->verify();
$file = App\Testing\BrowserRunGuard::DIRECTORY.'/read-performance.json';
if (is_file($file)) throw new RuntimeException('This owned run already has a performance fixture; measure it rather than reseeding.');
$accounts = json_decode(file_get_contents(App\Testing\BrowserRunGuard::DIRECTORY.'/accounts.json'), true, flags: JSON_THROW_ON_ERROR);
$owner = $em->getRepository(App\Entity\User::class)->findOneBy(['email' => $accounts['editor']['email']]);
$other = $em->getRepository(App\Entity\User::class)->findOneBy(['email' => $accounts['admin']['email']]);
$fixed = static function (object $entity, string $key): void {
    (new ReflectionProperty($entity, 'id'))->setValue($entity, (string) Symfony\Component\Uid\Ulid::fromBinary(substr(hash('sha256', 'read-performance-v1:'.$key, true), 0, 16)));
};
$factory = new App\Catalogue\SourceFactory(); $canonical = new Styleguide\Contracts\CanonicalJson();
$themes = new Styleguide\Contracts\ThemeValidator();
$validator = new Styleguide\Contracts\PackageValidator(new Styleguide\Contracts\PropsValidator(), $themes, new Styleguide\Contracts\ThemeCssGenerator($themes, $canonical), new Styleguide\Contracts\CompositionValidator());
$archives = new App\Packaging\ArchiveStore($canonical, '/app/var/catalogue');
$base = new DateTimeImmutable('2026-01-01T00:00:00Z'); $releases = 0; $favoriteCount = 0;
for ($index = 0; $index < 1040; $index++) {
    $namespace = $index < 1000 ? 'read-bench' : 'private-read-bench';
    $kind = $index % 4 === 0 ? 'layout' : ($index % 4 === 1 ? 'block' : 'component');
    $slug = sprintf('item-%04d', $index); $name = $namespace.'/'.$slug;
    $item = new App\Entity\CatalogueItem($namespace, $slug, $kind, 'Performance sample '.$slug, 'Representative catalogue read fixture.', ['read-bench', $index % 2 ? 'odd' : 'even']);
    $fixed($item, $name); (new ReflectionProperty($item, 'updatedAt'))->setValue($item, $base->modify('+'.$index.' seconds'));
    [$manifest, $sources] = $factory->initial(new App\Catalogue\CreateItemInput($name, $kind, $item->getTitle()));
    if ($kind === 'component') $sources->{$manifest->entry} .= str_repeat("<!-- Bounded source text, deliberately unnecessary for catalogue summaries. -->\n", 200);
    $manifest = $validator->validate($manifest, $sources, true);
    $em->persist($item); $em->persist(new App\Entity\Draft($item, $manifest, $sources, $owner));
    if ($index % 3 === 0) {$em->persist(new App\Entity\Favorite($index < 1000 ? $owner : $other, $item)); if ($index < 1000) $favoriteCount++;}
    if ($index < 128) for ($version = 0; $version < 12; $version++) {
        $m = clone $manifest; $m->version = '1.0.'.$version; [$key, $hash] = $archives->put($m, $sources);
        $release = new App\Entity\Release($item, $m->version, $m, $hash, $key); $fixed($release, $name.'@'.$m->version);
        (new ReflectionProperty($release, 'createdAt'))->setValue($release, $base->modify('+'.$version.' seconds'));
        $em->persist($release); $releases++;
    }
    if ($index % 50 === 49) $em->flush();
}
$em->flush();
for ($index = 0; $index < 128; $index++) {
    $family = new App\Entity\ThemeFamily('read-bench/family-'.$index, sprintf('Read benchmark family %03d', $index)); $fixed($family, 'family-'.$index); $em->persist($family);
    foreach ($index % 2 ? ['light'] : ['light','dark'] as $mode) {
        $name = sprintf('read-bench/theme-%03d-%s', $index, $mode); $document = App\Appearance\DefaultThemes::document($mode, $name); $themes->validate($document);
        $theme = new App\Entity\Theme($name, $document); $fixed($theme, $name); $em->persist($theme); $family->attach($theme);
    }
}
$name = 'read-bench/legacy-orphan'; $document = App\Appearance\DefaultThemes::document('light', $name); $themes->validate($document);
$legacy = new App\Entity\Theme($name, $document); $fixed($legacy, $name); $em->persist($legacy); $em->flush();
[, $plain] = (new App\Mcp\Security\ComposeTokens($em))->issue($owner, 'Isolated read benchmark', ['compose:read'], ['read-bench/*']);
file_put_contents($file, json_encode(['seed' => 'read-performance-v1', 'items' => 1040, 'scopedItems' => 1000, 'releases' => $releases, 'actorFavorites' => $favoriteCount, 'families' => 128, 'themes' => 193, 'legacyId' => $legacy->getId(), 'token' => $plain], JSON_THROW_ON_ERROR)); chmod($file, 0600);
$db->executeStatement('ANALYZE catalogue_item'); $db->executeStatement('ANALYZE draft'); $db->executeStatement('ANALYZE catalogue_release'); $db->executeStatement('ANALYZE theme'); $db->executeStatement('ANALYZE theme_family');
echo json_encode(['seed' => 'read-performance-v1', 'items' => 1040, 'scopedItems' => 1000, 'releases' => $releases, 'actorFavorites' => $favoriteCount, 'families' => 128, 'themes' => 193], JSON_THROW_ON_ERROR)."\n";
