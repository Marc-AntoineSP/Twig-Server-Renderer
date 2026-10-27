<?php
$root = dirname(__DIR__, 2); $destination = $argv[1] ?? '';
if (!str_starts_with($destination, $root.'/var/font-consumer-') || file_exists($destination)) throw new RuntimeException('Choose an absent task-owned font consumer directory.');
mkdir($destination, 0775, true);
$source = $root.'/tests/Consumer/project';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $file) {
    $path = $destination.'/'.substr($file->getPathname(), strlen($source) + 1);
    if ($file->isDir()) mkdir($path, 0775, true); else copy($file->getPathname(), $path);
}
file_put_contents($destination.'/.env.local', "APP_ENV=prod\nAPP_DEBUG=0\nAPP_SECRET=".bin2hex(random_bytes(32))."\n");
chmod($destination.'/.env.local', 0600);
file_put_contents($destination.'/.font-consumer-fixture', "Fresh font export verification fixture.\n");
$controller = $destination.'/src/Controller/DemoController.php';
file_put_contents($controller, str_replace("\$manifest['kind']!=='theme'", "\$manifest['kind']==='layout'", file_get_contents($controller)));
$importmap = require $destination.'/importmap.php';
$cached = require $root.'/assets/vendor/installed.php'; $installed = [];
foreach ($importmap as $name => $entry) {
    if (!isset($entry['version'])) continue;
    if (($cached[$name]['version'] ?? null) !== $entry['version'] || ($cached[$name]['dependencies'] ?? [])) throw new RuntimeException('No exact dependency-free cached importmap package for '.$name);
    $directory = $root.'/assets/vendor/'.$name;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || $file->isLink()) throw new RuntimeException('Invalid cached importmap asset.');
        $target = $destination.'/assets/vendor/'.$name.'/'.substr($file->getPathname(), strlen($directory) + 1);
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
        copy($file->getPathname(), $target);
        if (hash_file('sha256', $target) !== hash_file('sha256', $file->getPathname())) throw new RuntimeException('Cached dependency copy differs.');
    }
    $installed[$name] = $cached[$name];
}
file_put_contents($destination.'/assets/vendor/installed.php', '<?php return '.var_export($installed, true).';');
echo "Fresh isolated font consumer created; existing consumers preserved.\n";
