<?php

namespace App\Tests\Functional;

use App\Command\CreateUserCommand;
use App\Entity\User;
use App\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

final class AuthenticationTest extends DatabaseTestCase
{
    public function testCatalogueIsPrivateAndLoginRequiresCsrf(): void
    {
        $client = self::createClient();
        $user = $this->account();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $client->request('POST', '/login', ['_username' => $user->getEmail(), '_password' => 'Test password 2026!']);
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Invalid CSRF token');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testRealFormLoginAndCsrfProtectedLogout(): void
    {
        $client = self::createClient();
        $user = $this->account('ROLE_ADMIN');
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['_username' => $user->getEmail(), '_password' => 'Test password 2026!']);
        self::assertResponseRedirects('http://localhost/');
        $client->followRedirect();
        self::assertSelectorTextContains('#account-options', $user->getEmail());
        $client->request('POST', '/logout');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/');
        $client->submitForm('Sign out');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testDisabledAccountCannotLogin(): void
    {
        $client = self::createClient();
        $user = $this->account();
        $user->disable();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['_username' => $user->getEmail(), '_password' => 'Test password 2026!']);
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testInteractiveAdminCreationPersistsOnlyAHash(): void
    {
        self::bootKernel();
        $email = strtolower((string) new Ulid()).'@example.test';
        $tester = new CommandTester(self::getContainer()->get(CreateUserCommand::class));
        $tester->setInputs([$email, 'An interactive secret!', 'An interactive secret!']);
        $tester->execute(['--admin' => true], ['interactive' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('An interactive secret!', $tester->getDisplay());
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'An interactive secret!'));
        self::assertNotSame('An interactive secret!', $user->getPassword());
    }

    public function testDuplicateEmailsAreCanonicalAndRejected(): void
    {
        self::bootKernel();
        $user = $this->account();
        $this->expectException(\InvalidArgumentException::class);
        self::getContainer()->get(UserCreator::class)->create(strtoupper($user->getEmail()), 'Another password 2026!');
    }

    public function testNoninteractiveCreationFailsWithoutAPasswordPrompt(): void
    {
        self::bootKernel();
        $tester = new CommandTester(self::getContainer()->get(CreateUserCommand::class));
        $tester->execute(['--admin' => true], ['interactive' => false]);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }
}
