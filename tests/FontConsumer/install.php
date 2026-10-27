<?php
require '/consumer/vendor/autoload.php';
use Symfony\Component\Process\Process;
use Styleguide\Contracts\SafeArchive;
use Styleguide\Contracts\SourceBytes;

function command(array $args): array {
    $process = new Process(['php', 'bin/console', ...$args, '--no-interaction'], '/consumer');
    $process->setTimeout(120); $process->mustRun();
    $text = trim($process->getOutput());
    return str_starts_with($text, '{') ? json_decode(substr($text, 0, strrpos($text, '}') + 1), true, 32, JSON_THROW_ON_ERROR) : [];
}
$root = '/workspace/var/library-verification';
$plan = command(['styleguide:install', $root.'/font-collection.zip', '--dry-run']);
if (!$plan['dryRun']) throw new RuntimeException('Dry run failed.');
command(['styleguide:install', $root.'/font-collection.zip']);
$again = command(['styleguide:install', $root.'/font-collection.zip']);
if ($again['changed'] !== 0) throw new RuntimeException('Font reinstall was not idempotent.');
$state = json_decode(file_get_contents($root.'/consumer-state.json'), true, 32, JSON_THROW_ON_ERROR);
$tokens = $state['tokens']; $base = '/consumer/assets/styles/catalogue/'.$tokens['name'].'/';
foreach ($tokens['fonts'] as $font) {
    if ($font['kind'] !== 'local') continue;
    foreach ($font['faces'] as $face) {
        $bytes = file_get_contents($base.$face['path']); SourceBytes::validateFont($bytes);
        if (hash('sha256', $bytes) !== $face['sha256'] || strlen($bytes) !== $face['bytes']) throw new RuntimeException('Installed font integrity mismatch.');
    }
    if (hash_file('sha256', $base.$font['licensePath']) !== $font['licenseSha256']) throw new RuntimeException('Installed font licence mismatch.');
}
$installed = json_decode(file_get_contents('/consumer/.styleguide/packages/'.$tokens['name'].'/1.0.0/meta/theme.json'), true, 32, JSON_THROW_ON_ERROR);
$canonical = new \Styleguide\Contracts\CanonicalJson();
if ($canonical->encode(json_decode(json_encode($installed))) !== $canonical->encode(json_decode(json_encode($tokens)))) throw new RuntimeException('Canonical typography, style or axis data changed in the consumer.');
$css = file_get_contents('/consumer/assets/styles/app.css');
if (str_contains($css, '.styleguide/staging') || preg_match('~url\([\'\"]?https?://~i', $css)) throw new RuntimeException('Compiled font URLs retain a staging or server dependency.');
command(['asset-map:compile', '--env=prod', '--no-debug']); command(['cache:clear', '--env=prod', '--no-debug']);
foreach (['font-static.zip', 'font-theme-bundle.zip'] as $archive) {
    $files = (new SafeArchive())->read($root.'/'.$archive);
    $inventory = isset($files['manifest.json']) ? array_column(json_decode($files['manifest.json'], true, 32, JSON_THROW_ON_ERROR)['files'], null, 'path') : json_decode($files['checksums.json'], true, 32, JSON_THROW_ON_ERROR);
    foreach ($inventory as $path => $file) if (!isset($files[$path]) || strlen($files[$path]) !== $file['bytes'] || hash('sha256', $files[$path]) !== $file['sha256']) throw new RuntimeException('Export inventory mismatch.');
    if ('font-static.zip' === $archive) {
        $destination = $root.'/static'; if (!is_dir($destination)) mkdir($destination, 0775, true);
        foreach ($files as $path => $bytes) { if (!is_dir(dirname($destination.'/'.$path))) mkdir(dirname($destination.'/'.$path), 0775, true); file_put_contents($destination.'/'.$path, $bytes); }
    }
}
echo "PASS: clean consumer installed and decoded font files, exact metadata/licences/checksums, changed=0 reinstall and production AssetMapper compilation; static/theme inventories verified.\n";
