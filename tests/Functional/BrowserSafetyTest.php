<?php

namespace App\Tests\Functional;

use App\Tests\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Doctrine\DBAL\Connection;

final class BrowserSafetyTest extends DatabaseTestCase
{
    public function testOrdinaryTestDatabaseCannotAuthorizeBrowserMutationsOrCleanup(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        $before = $db->fetchOne('SELECT count(*) FROM studio_user');
        $application = new Application(self::$kernel);
        foreach (['styleguide:test:accounts' => ['--cleanup' => true], 'styleguide:test:seed' => []] as $name => $arguments) {
            $failure = null;
            try {
                (new CommandTester($application->find($name)))->execute($arguments);
            } catch (\Throwable $error) {
                $failure = $error;
            }
            self::assertNotNull($failure, 'An ordinary database must never authorize browser mutation.');
            self::assertStringContainsString('browser', strtolower($failure->getMessage()));
        }
        self::assertSame($before, $db->fetchOne('SELECT count(*) FROM studio_user'));
    }

    public function testOrdinaryApplicationDoesNotProvideOwnershipProof(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health/browser-run?challenge='.str_repeat('a', 64));
        self::assertResponseStatusCodeSame(404);
        self::assertStringNotContainsString('proof', $client->getResponse()->getContent());
    }
}
