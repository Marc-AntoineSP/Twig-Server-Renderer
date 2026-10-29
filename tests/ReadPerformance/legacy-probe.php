<?php
// Read-only probe; prepare the single owned orphan explicitly with legacy.php detach.
require getcwd().'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(getcwd().'/.env');
$kernel = new App\Tests\ReadPerformance\MeasuredKernel('dev', true); $kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
(new App\Testing\BrowserRunGuard($db))->verify();
$p = App\Testing\BrowserRunGuard::DIRECTORY;
$f = json_decode(file_get_contents($p.'/read-performance.json'), true, flags: JSON_THROW_ON_ERROR);
$a = json_decode(file_get_contents($p.'/accounts.json'), true, flags: JSON_THROW_ON_ERROR)['editor'];
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->request('GET', '/login');
$client->submit($client->getCrawler()->selectButton('Sign in')->form(['_username' => $a['email'], '_password' => $a['password']]));
$before = (int) $db->fetchOne('SELECT COUNT(*) FROM theme_family');
$client->request('GET', '/api/v1/themes/'.$f['legacyId']);
if ($client->getResponse()->getStatusCode() !== 200) throw new RuntimeException('Legacy read failed.');
$writes = [];
foreach ($client->getContainer()->get('doctrine.debug_data_holder')->getData() as $queries) foreach ($queries as $q) if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $q['sql'])) $writes[] = $q['sql'];
$after = (int) $db->fetchOne('SELECT COUNT(*) FROM theme_family');
$data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['familiesBefore' => $before, 'familiesAfter' => $after, 'writes' => $writes, 'visibleModes' => array_keys($data['family']['variants'])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
