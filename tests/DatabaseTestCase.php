<?php

namespace App\Tests;

use App\Entity\User;
use App\Security\UserCreator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

abstract class DatabaseTestCase extends WebTestCase
{
    protected function account(string $role = 'ROLE_EDITOR'): User
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertStringEndsWith('_test', $connection->fetchOne('SELECT current_database()'));

        return self::getContainer()->get(UserCreator::class)->create(
            strtolower((string) new Ulid()).'@example.test', 'Test password 2026!', $role,
        );
    }

    protected function signIn(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }
}
