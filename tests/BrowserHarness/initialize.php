<?php

use App\Testing\BrowserRunGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$environment = getenv('APP_ENV') ?: 'dev';
$kernel = new App\Kernel($environment, false);
$kernel->boot();
$connection = $kernel->getContainer()->get('doctrine')->getConnection();
$guard = new BrowserRunGuard($connection);
$owner = $guard->ownership();
if ($connection->fetchOne('SELECT current_database()') !== ($environment === 'prod' ? 'styleguide' : 'browser')) throw new RuntimeException('Refusing a non-browser database.');
if ($connection->fetchOne("SELECT shobj_description(oid, 'pg_database') FROM pg_database WHERE datname=current_database()")) {
    $guard->verify();
    exit;
}
if ($connection->fetchOne("SELECT count(*) FROM information_schema.tables WHERE table_schema='public'") !== 0 || count(scandir('/app/var/catalogue')) !== 2) {
    throw new RuntimeException('Ownership initialization requires a completely empty database and blob volume.');
}
$sentinel = ['run_id' => $owner['runId'], 'secret_hash' => hash('sha256', $owner['secret'])];
$connection->executeStatement('COMMENT ON DATABASE '.($environment === 'prod' ? 'styleguide' : 'browser').' IS '.$connection->quote(json_encode($sentinel, JSON_THROW_ON_ERROR)));
file_put_contents('/app/var/catalogue/.browser-test-ownership.json', json_encode($sentinel, JSON_THROW_ON_ERROR));
$guard->verify();
echo "Disposable database and blob sentinels initialized.\n";
