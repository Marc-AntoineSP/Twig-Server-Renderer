<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BootstrapTest extends WebTestCase
{
    public function testHomeUsesTwigAndLocalAssets(): void
    {
        $client = self::createClient();
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
