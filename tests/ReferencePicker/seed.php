<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/Fixtures/ReferenceCatalogue.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel(getenv('APP_ENV') ?: 'dev', false); $kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
(new App\Testing\BrowserRunGuard($em->getConnection()))->verify();
$directory = App\Testing\BrowserRunGuard::DIRECTORY;
$file = $directory.'/reference-fixture.json';
if (is_file($file)) { echo "Owned reference load fixture already exists.\n"; exit; }
$accounts = json_decode(file_get_contents($directory.'/accounts.json'), true, 8, JSON_THROW_ON_ERROR);
$owner = $em->getRepository(App\Entity\User::class)->findOneBy(['email' => $accounts['editor']['email']]);
if (!$owner || !$owner->isEnabled()) throw new RuntimeException('An owned active editor is required.');
$fixture = App\Tests\Fixtures\ReferenceCatalogue::create($em, $owner, 'picker-load');
file_put_contents($file, json_encode($fixture, JSON_THROW_ON_ERROR)); chmod($file, 0600);
echo json_encode(['seededReleases' => $fixture['releaseCount'], 'favoriteItems' => $fixture['favoriteItems']], JSON_THROW_ON_ERROR)."\n";
