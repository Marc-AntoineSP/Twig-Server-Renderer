<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Tests\ReadPerformance\MeasuredKernel('dev', true); $kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager(); $db = $em->getConnection();
(new App\Testing\BrowserRunGuard($db))->verify();
$private = App\Testing\BrowserRunGuard::DIRECTORY;
$fixture = json_decode(file_get_contents($private.'/read-performance.json'), true, flags: JSON_THROW_ON_ERROR);
$accounts = json_decode(file_get_contents($private.'/accounts.json'), true, flags: JSON_THROW_ON_ERROR);
$repeats = (int) ($argv[1] ?? 5); if ($repeats < 3 || $repeats > 10) throw new RuntimeException('Use 3–10 measured repetitions.');
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->request('GET', '/login');
$client->submit($client->getCrawler()->selectButton('Sign in')->form(['_username' => $accounts['editor']['email'], '_password' => $accounts['editor']['password']]));
$sequence = 0; $mcpHeaders = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$fixture['token']];
$rpc = function (string $method, mixed $params, bool $notification = false) use ($client, &$mcpHeaders, &$sequence): void {
    $body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params]; if (!$notification) $body['id'] = ++$sequence;
    $client->request('POST', '/mcp/compose', server: $mcpHeaders, content: json_encode($body, JSON_THROW_ON_ERROR));
    if ($session = $client->getResponse()->headers->get('Mcp-Session-Id')) $mcpHeaders['HTTP_MCP_SESSION_ID'] = $session;
};
$rpc('initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'isolated-read-benchmark', 'version' => '1']]);
if ($client->getResponse()->getStatusCode() !== 200) throw new RuntimeException('MCP initialization failed.');
$mcpHeaders['HTTP_MCP_PROTOCOL_VERSION'] = '2025-11-25';
$rpc('notifications/initialized', (object) [], true);
$cases = [
    'catalogue-name' => ['GET', '/catalogue?q=Performance+sample&sort=name'],
    'catalogue-recent-page2' => ['GET', '/catalogue?q=Performance+sample&page=2'],
    'catalogue-favorites' => ['GET', '/catalogue?q=Performance+sample&sort=name&favorites=1'],
    'themes-page' => ['GET', '/themes?q=read-bench%2Ftheme-&sort=name'],
    'appearance-editor' => ['GET', '/api/v1/appearance/families'],
];
foreach ([1,20,50] as $limit) $cases['mcp-'.$limit] = ['MCP', ['query' => 'Performance sample', 'limit' => $limit]];
$cases['mcp-favorites'] = ['MCP', ['query' => 'Performance sample', 'limit' => 50, 'favoritesOnly' => true]];
$results = []; $planInputs = [];
foreach ($cases as $name => [$method, $input]) {
    $samples = [];
    for ($iteration = -1; $iteration < $repeats; $iteration++) {
        $started = hrtime(true);
        if ($method === 'MCP') $rpc('tools/call', ['name' => 'compose_search_catalogue', 'arguments' => $input]);
        else $client->request($method, $input);
        $elapsed = (hrtime(true) - $started) / 1e6;
        $response = $client->getResponse(); $content = $response->getContent();
        if ($content === false) {ob_start(); $response->sendContent(); $content = ob_get_clean();}
        if ($response->getStatusCode() !== 200) throw new RuntimeException('Measurement failed: '.$name.' HTTP '.$response->getStatusCode());
        if ($method === 'MCP' && !str_contains($content, 'Accessible catalogue results.')) throw new RuntimeException('Authenticated MCP search did not succeed.');
        $queries = []; $sqlSeconds = 0;
        foreach ($client->getContainer()->get('doctrine.debug_data_holder')->getData() as $connection) foreach ($connection as $query) {$queries[] = $query['sql']; $sqlSeconds += $query['executionMS']; if ($iteration === 0 && $name === 'catalogue-favorites' && preg_match('/^SELECT (COUNT|id FROM catalogue_item)/', $query['sql'])) $planInputs[] = $query;}
        $writes = array_values(array_filter($queries, static fn ($sql) => preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $sql)));
        $sample = ['elapsedMs' => round($elapsed, 3), 'databaseMs' => round($sqlSeconds * 1000, 3), 'sqlQueries' => count($queries), 'responseBytes' => strlen($content), 'writes' => count($writes)];
        if ($iteration >= 0) $samples[] = $sample;
        if ($iteration === 0) {
            $sql = $queries;
            if ($method === 'MCP') {
                if (str_starts_with($response->headers->get('Content-Type', ''), 'text/event-stream')) {preg_match_all('/^data: (.+)$/m', $content, $matches); $body = json_decode(end($matches[1]));} else $body = json_decode($content);
                $semantic = $body->result->structuredContent;
            } elseif (str_starts_with($input, '/api/')) $semantic = json_decode($content);
            else $semantic = $client->getCrawler()->filter('main article.card')->each(static fn ($node) => ['text' => $node->text(null, true), 'links' => $node->filter('h2 a')->each(static fn ($a) => $a->attr('href'))]);
            $semanticHash = hash('sha256', (new Styleguide\Contracts\CanonicalJson())->encode(json_decode(json_encode($semantic))));
        }
    }
    $results[$name] = ['request' => $input, 'samples' => $samples, 'semanticHash' => $semanticHash, 'sql' => $sql];
}
$counts = []; foreach (['catalogue_item', 'draft', 'catalogue_release', 'personal_favorite', 'theme', 'theme_family'] as $table) $counts[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$table);
$plans = [];
foreach ([
    'catalogue-page' => ["SELECT id FROM catalogue_item WHERE LOWER(namespace || '/' || slug) LIKE :q OR LOWER(title) LIKE :q ORDER BY namespace,slug LIMIT 12", ['q' => '%performance sample%']],
    'releases-per-item' => ['SELECT id,version FROM catalogue_release WHERE item_id=:id ORDER BY created_at DESC LIMIT 10', ['id' => (string) Symfony\Component\Uid\Ulid::fromBinary(substr(hash('sha256', 'read-performance-v1:read-bench/item-0000', true), 0, 16))]],
    'appearance-choices' => ['SELECT id,label,light_theme_id,dark_theme_id FROM theme_family ORDER BY label', []],
] as $name => [$statement, $params]) $plans[$name] = json_decode($db->fetchOne('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$statement, $params), true);
foreach ($planInputs as $i => $query) $plans['observed-favorites-'.$i] = json_decode($db->fetchOne('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query['sql'], $query['params'], $query['types']), true);
echo json_encode(['seed' => $fixture['seed'], 'counts' => $counts, 'actor' => 'enabled editor; MCP compose:read restricted to read-bench/*', 'runtime' => ['php' => PHP_VERSION, 'symfony' => Symfony\Component\HttpKernel\Kernel::VERSION, 'postgres' => $db->fetchOne('SHOW server_version'), 'cpus' => trim(shell_exec('getconf _NPROCESSORS_ONLN')), 'memoryLimit' => ini_get('memory_limit')], 'conditions' => 'KernelBrowser dev/debug=true; in-memory DBAL debug middleware only; MCP persisted profiling remains disabled; one excluded warmup per case, fresh request entity manager; warm PostgreSQL/OS caches; no TCP/network time; complete Symfony/auth/SSR lifecycle including debug middleware overhead', 'repetitions' => $repeats, 'results' => $results, 'plans' => $plans], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
