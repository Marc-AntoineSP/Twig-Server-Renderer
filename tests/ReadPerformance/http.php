<?php
// Real HTTP timings on the serving app, separate from the instrumented in-process run.
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$k = new App\Kernel('dev', false); $k->boot();
(new App\Testing\BrowserRunGuard($k->getContainer()->get('doctrine')->getConnection()))->verify();
$p = App\Testing\BrowserRunGuard::DIRECTORY;
$f = json_decode(file_get_contents($p.'/read-performance.json'), true, flags: JSON_THROW_ON_ERROR);
$a = json_decode(file_get_contents($p.'/accounts.json'), true, flags: JSON_THROW_ON_ERROR)['editor'];
$jar = tempnam(sys_get_temp_dir(), 'read-http-'); chmod($jar, 0600); $curl = curl_init();
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false]);
$request = function (string $method, string $path, ?string $body = null, array $headers = []) use ($curl): array {
    $responseHeaders = [];
    curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $line) use (&$responseHeaders): int {if (str_contains($line, ':')) {[$key, $value] = explode(':', $line, 2); $responseHeaders[strtolower(trim($key))] = trim($value);} return strlen($line);});
    curl_setopt_array($curl, [CURLOPT_URL => 'http://studio'.$path, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers]);
    $content = curl_exec($curl); if ($content === false) throw new RuntimeException('Owned HTTP measurement failed.');
    return ['mcpSession' => $responseHeaders['mcp-session-id'] ?? null, 'status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'elapsedMs' => curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000, 'connectMs' => curl_getinfo($curl, CURLINFO_CONNECT_TIME) * 1000, 'ttfbMs' => curl_getinfo($curl, CURLINFO_STARTTRANSFER_TIME) * 1000, 'responseBytes' => strlen($content), 'content' => $content];
};
try {
    $login = $request('GET', '/login'); $crawler = new Symfony\Component\DomCrawler\Crawler($login['content'], 'http://studio/login');
    $values = $crawler->selectButton('Sign in')->form()->getValues(); $values['_username'] = $a['email']; $values['_password'] = $a['password'];
    $answer = $request('POST', '/login', http_build_query($values), ['Content-Type: application/x-www-form-urlencoded']);
    if ($answer['status'] !== 302) throw new RuntimeException('Owned HTTP login failed.');
    $mcpHeaders = ['Authorization: Bearer '.$f['token'], 'Content-Type: application/json', 'Accept: application/json, text/event-stream'];
    $initialized = $request('POST', '/mcp/compose', json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'isolated-http-read-benchmark', 'version' => '1']]], JSON_THROW_ON_ERROR), $mcpHeaders);
    if ($initialized['status'] !== 200) throw new RuntimeException('Owned HTTP MCP initialization failed.');
    $mcpHeaders[] = 'MCP-Protocol-Version: 2025-11-25';
    if ($initialized['mcpSession']) $mcpHeaders[] = 'MCP-Session-Id: '.$initialized['mcpSession'];
    $request('POST', '/mcp/compose', json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => (object) []], JSON_THROW_ON_ERROR), $mcpHeaders);
    $cases = ['catalogue-name' => '/catalogue?q=Performance+sample&sort=name', 'catalogue-recent-page2' => '/catalogue?q=Performance+sample&page=2', 'catalogue-favorites' => '/catalogue?q=Performance+sample&sort=name&favorites=1', 'themes-page' => '/themes?q=read-bench%2Ftheme-&sort=name', 'appearance-editor' => '/api/v1/appearance/families'];
    foreach ([1,20,50] as $limit) $cases['mcp-'.$limit] = ['query' => 'Performance sample', 'limit' => $limit];
    $cases['mcp-favorites'] = ['query' => 'Performance sample', 'limit' => 50, 'favoritesOnly' => true];
    $results = []; $sequence = 1;
    foreach ($cases as $name => $input) {
        $samples = [];
        for ($i = -1; $i < 5; $i++) {
            $r = is_string($input) ? $request('GET', $input) : $request('POST', '/mcp/compose', json_encode(['jsonrpc' => '2.0', 'id' => $sequence++, 'method' => 'tools/call', 'params' => ['name' => 'compose_search_catalogue', 'arguments' => $input]], JSON_THROW_ON_ERROR), $mcpHeaders);
            if ($r['status'] !== 200 || !is_string($input) && !str_contains($r['content'], 'Accessible catalogue results.')) throw new RuntimeException('HTTP read failed: '.$name);
            unset($r['content'], $r['mcpSession']); if ($i >= 0) $samples[] = $r;
        }
        $results[$name] = ['request' => $input, 'samples' => $samples];
    }
    echo json_encode(['seed' => $f['seed'], 'conditions' => 'Real HTTP to isolated FrankenPHP/Caddy app, dev/debug=false; no SQL profiling; one excluded warmup +5 measurements per case; local internal Docker network; curl response bytes decoded', 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
} finally {curl_setopt($curl, CURLOPT_COOKIEJAR, null); unset($request, $curl); unlink($jar);}
