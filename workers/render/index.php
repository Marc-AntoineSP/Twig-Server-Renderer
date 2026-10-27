<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Process\Process;

header('Content-Type: application/json');
header('Cache-Control: no-store');
if ('/health' === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    echo '{"status":"ok"}';
    return;
}
$token = getenv('WORKER_TOKEN_FILE') ? trim(file_get_contents(getenv('WORKER_TOKEN_FILE'))) : getenv('WORKER_TOKEN');
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$token || !hash_equals('Bearer '.$token, $authorization)) {
    http_response_code(403);
    echo '{"ok":false,"error":{"code":"forbidden","message":"Worker authentication required."}}';
    return;
}
if ('POST' !== $_SERVER['REQUEST_METHOD'] || '/render' !== parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    http_response_code(404);
    echo '{"ok":false,"error":{"code":"not_found","message":"Unknown worker operation."}}';
    return;
}
$body = file_get_contents('php://input', false, null, 0, 3145729);
if (strlen($body) > 3145728) {
    http_response_code(413);
    echo '{"ok":false,"error":{"code":"input_limit","message":"Input exceeds 3 MiB."}}';
    return;
}
try {
    $environment = array_fill_keys(array_keys(getenv()), false);
    $environment['PATH'] = '/usr/local/bin:/usr/bin:/bin';
    $environment['LANG'] = 'C.UTF-8';
    $process = new Process([
        PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'display_errors=0', '-d', 'allow_url_fopen=0',
        '-d', 'open_basedir=/worker:/tmp',
        '-d', 'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,dl,putenv',
        __DIR__.'/render.php',
    ], __DIR__, $environment, $body, 2.0);
    if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,static function()use($process):void{$process->stop(0.2);exit(0);});}
    $bytes = 0;
    $process->run(static function (string $type, string $buffer) use (&$bytes, $process): void {
        $bytes += strlen($buffer);
        if ($bytes > 1200000) {
            $process->stop(0);
            throw new RuntimeException('Output limit.');
        }
    });
    if (!$process->isSuccessful()) {
        throw new RuntimeException('Child resource limit.');
    }
    $result = json_decode($process->getOutput(), true, 24, JSON_THROW_ON_ERROR);
    if (!isset($result['ok'])) {
        throw new RuntimeException('Invalid child result.');
    }
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable) {
    http_response_code(422);
    echo '{"ok":false,"error":{"code":"render_resource_limit","message":"Rendering exceeded its time, memory or output budget."}}';
}
