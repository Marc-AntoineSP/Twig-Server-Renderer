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
        self::assertSelectorTextContains('h1', 'Build a language');
        self::assertSelectorExists('[data-action="studio#toggle"]');
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
