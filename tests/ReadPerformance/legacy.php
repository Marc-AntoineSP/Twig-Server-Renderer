<?php
// Explicit fixture mutation, separate from the read measurement and guarded twice.
if (!in_array($argv[1] ?? '', ['restore', 'detach'], true)) throw new RuntimeException('Use restore or detach.');
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('dev', false); $kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager(); $db = $em->getConnection();
(new App\Testing\BrowserRunGuard($db))->verify();
$fixture = json_decode(file_get_contents(App\Testing\BrowserRunGuard::DIRECTORY.'/read-performance.json'), true, flags: JSON_THROW_ON_ERROR);
$theme = $em->find(App\Entity\Theme::class, $fixture['legacyId']);
if (!$theme || $theme->getName() !== 'read-bench/legacy-orphan') throw new RuntimeException('Legacy fixture identity mismatch.');
$ids = $db->fetchFirstColumn('SELECT id FROM theme_family WHERE light_theme_id=? OR dark_theme_id=?', [$theme->getId(), $theme->getId()]);
foreach ($ids as $id) {
    if ($db->fetchOne('SELECT 1 FROM appearance_preference WHERE family_id=?', [$id])) throw new RuntimeException('Fixture acquired a preference; refusing repair/reset.');
    $family = $em->find(App\Entity\ThemeFamily::class, $id);
    if ($family->isBuiltIn() || count($family->variants()) !== 1) throw new RuntimeException('Not an isolated legacy fixture family.');
    $em->remove($family);
}
$em->flush();
if (($argv[1] ?? '') === 'restore') {
    $family = new App\Entity\ThemeFamily($theme->getName(), $theme->getName());
    (new ReflectionProperty($family, 'id'))->setValue($family, (string) Symfony\Component\Uid\Ulid::fromBinary(substr(hash('sha256', 'read-performance-v1:legacy-fixture-family', true), 0, 16)));
    $family->attach($theme); $em->persist($family); $em->flush();
} elseif (($argv[1] ?? '') !== 'detach') throw new RuntimeException('Use restore or detach.');
echo "Owned legacy fixture state prepared.\n";
