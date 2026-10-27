<?php
// A fresh consumer is copied only into an absent directory or this fixture's own marker.
$root=dirname(__DIR__,2);$destination=$root.'/var/consumer';$source=__DIR__.'/project';
if(is_dir($destination)&&!is_file($destination.'/.styleguide-test-fixture'))throw new RuntimeException('Refusing to replace an unknown consumer directory.');
if(is_dir($destination)){
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($destination);
}
mkdir($destination,0775,true);$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
foreach($iterator as $file){$path=$destination.'/'.substr($file->getPathname(),strlen($source)+1);if($file->isDir())mkdir($path,0775,true);else copy($file->getPathname(),$path);}
mkdir($destination.'/var',0775,true);$env=$root.'/var/consumer-runtime.env';file_put_contents($env,'APP_ENV=prod'.PHP_EOL.'APP_DEBUG=0'.PHP_EOL.'APP_SECRET='.bin2hex(random_bytes(32)).PHP_EOL);chmod($env,0600);
echo "Fresh marked consumer fixture copied. Runtime secret generated in private ignored storage.\n";
