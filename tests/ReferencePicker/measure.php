<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/Fixtures/ReferenceCatalogue.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__,2).'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
(new App\Testing\BrowserRunGuard($em->getConnection()))->verify();
$file = App\Testing\BrowserRunGuard::DIRECTORY.'/reference-fixture.json';
$owner = $em->getRepository(App\Entity\User::class)->findOneBy(['email' => 'editor@browser.example.test']);
if (!$owner) throw new RuntimeException('An owned seeded editor is required.');
if (($argv[1] ?? '') === 'seed') {
    if (is_file($file)) throw new RuntimeException('This run already has a reference fixture.');
    $fixture = App\Tests\Fixtures\ReferenceCatalogue::create($em, $owner, 'picker-load');
    file_put_contents($file, json_encode($fixture, JSON_THROW_ON_ERROR)); chmod($file, 0600);
    echo json_encode(['seededReleases' => $fixture['releaseCount'], 'favoriteItems' => $fixture['favoriteItems']], JSON_THROW_ON_ERROR)."\n";
    exit;
}
$fixture = json_decode(file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
$workspace = json_decode(file_get_contents(App\Testing\BrowserRunGuard::DIRECTORY.'/fixtures.json'), true)['workspace']['id'];
$account = json_decode(file_get_contents(App\Testing\BrowserRunGuard::DIRECTORY.'/accounts.json'), true)['editor'];
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->request('GET', '/login');
$client->submit($client->getCrawler()->selectButton('Sign in')->form(['_username' => $account['email'], '_password' => $account['password']]));
$client->enableProfiler();
$client->request('GET', '/catalogue/'.$workspace.'/compose');
if ($client->getResponse()->getStatusCode() !== 200) throw new RuntimeException('Composition measurement request failed.');
$profile = $client->getProfile();
$choices = $client->getCrawler()->filter('[data-composition-choices-value]');
$choiceJson = $choices->count() ? $choices->attr('data-composition-choices-value') : '[]';
$metrics = ['htmlBytes' => strlen($client->getResponse()->getContent()), 'preloadedChoices' => count(json_decode($choiceJson, true)), 'choiceJsonBytes' => $choices->count() ? strlen($choiceJson) : 0, 'sqlQueries' => $profile?->getCollector('db')->getQueryCount(), 'oldReferenceIncluded' => str_contains($choiceJson, $fixture['old']['name'])];
if (class_exists(App\Composition\ReferenceDiscovery::class)) {
    $tree = json_decode($client->getCrawler()->filter('[data-composition-tree-value]')->attr('data-composition-tree-value'), true);
    $revision = (int) $client->getCrawler()->filter('[data-composition-revision-value]')->attr('data-composition-revision-value');
    $client->request('GET', '/api/v1/csrf'); $csrf = json_decode($client->getResponse()->getContent())->token;
    $client->enableProfiler();
    $client->jsonRequest('POST', '/api/v1/items/'.$workspace.'/composition/references', ['document' => $tree, 'expectedRevision' => $revision, 'selectedNodeId' => $tree['root']['id'], 'query' => ['q' => 'picker-load']], ['HTTP_X_CSRF_TOKEN' => $csrf]);
    if (200 !== $client->getResponse()->getStatusCode()) throw new RuntimeException('Discovery measurement failed: '.$client->getResponse()->getContent());
    $metrics['discoveryBytes'] = strlen($client->getResponse()->getContent());
    $metrics['discoveryQueries'] = $client->getProfile()->getCollector('db')->getQueryCount();
    $results = json_decode($client->getResponse()->getContent(), true)['items'];
    $metrics['discoveryRows'] = count($results);
    $metrics['discoveryIncludesOld'] = in_array($fixture['old']['name'], array_column($results, 'name'), true);
}
echo json_encode($metrics, JSON_THROW_ON_ERROR)."\n";
