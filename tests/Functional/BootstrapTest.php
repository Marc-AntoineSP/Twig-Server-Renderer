<?php

namespace App\Tests\Functional;

use App\Tests\DatabaseTestCase;

final class BootstrapTest extends DatabaseTestCase
{
    public function testHomeUsesTwigAndLocalAssets(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Your workspace');
        self::assertSelectorTextContains('#recent-items h2', 'Recently updated');
        self::assertSelectorTextContains('#personal-favorites h2', 'Your favorites');
        self::assertSelectorExists('script[type="importmap"]');
    }

    public function testLivenessAndDatabaseReadiness(): void
    {
        $client = self::createClient();
        foreach (['/health' => 'ok', '/health/ready' => 'ready'] as $path => $status) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertJsonStringEqualsJsonString(json_encode(['status' => $status]), $client->getResponse()->getContent());
        }
    }
}
