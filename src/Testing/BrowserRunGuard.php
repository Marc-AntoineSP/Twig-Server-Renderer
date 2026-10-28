<?php

namespace App\Testing;

use Doctrine\DBAL\Connection;

/** A flag or a URL is never sufficient proof that a catalogue is disposable. */
final readonly class BrowserRunGuard
{
    public const DIRECTORY = '/run/styleguide-test';

    public function __construct(private Connection $connection) {}

    public function ownership(): array
    {
        $path = self::DIRECTORY.'/ownership.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Browser mutations require the disposable browser harness. Use make browser.');
        }
        $owner = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        $run = $_SERVER['STUDIO_TEST_RUN_ID'] ?? getenv('STUDIO_TEST_RUN_ID');
        if (!preg_match('/^browser-[a-f0-9]{24}$/D', $owner['runId'] ?? '') || $run !== $owner['runId'] || !preg_match('/^[a-f0-9]{64}$/D', $owner['secret'] ?? '')) {
            throw new \RuntimeException('Browser run configuration does not match its private ownership file.');
        }
        return $owner;
    }

    public function verify(): array
    {
        $owner = $this->ownership();
        $expected = ['run_id' => $owner['runId'], 'secret_hash' => hash('sha256', $owner['secret'])];
        $database = ($_SERVER['APP_ENV'] ?? getenv('APP_ENV')) === 'prod' ? 'styleguide' : 'browser';
        if ($this->connection->fetchOne('SELECT current_database()') !== $database) {
            throw new \RuntimeException('Browser ownership cannot authorize this database.');
        }
        $row = json_decode($this->connection->fetchOne("SELECT shobj_description(oid, 'pg_database') FROM pg_database WHERE datname=current_database()") ?: '{}', true);
        $blob = json_decode(@file_get_contents('/app/var/catalogue/.browser-test-ownership.json') ?: '{}', true);
        if ($row !== $expected || $blob !== $expected) {
            throw new \RuntimeException('Disposable database and blob ownership must both match this run.');
        }
        return $owner;
    }
}
