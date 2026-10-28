<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$kernel = new App\Kernel(getenv('APP_ENV') ?: 'dev', false);
$kernel->boot();
$guard = new App\Testing\BrowserRunGuard($kernel->getContainer()->get('doctrine')->getConnection());
$guard->verify();
echo "Verified disposable database and blob ownership.\n";
