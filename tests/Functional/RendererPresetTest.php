<?php

namespace App\Tests\Functional;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Uid\Ulid;

final class RendererPresetTest extends DatabaseTestCase
{
    public function testRendererSettingsPersistPrivatelyWithoutChangingDraft(): void
    {
        $client = self::createClient();
        $owner = $this->account();
        $this->signIn($client, $owner);
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('presets/item-'.strtolower((string) new Ulid()), 'component', 'Preset fixture'), $owner);
        $id = $draft->getItem()->getId();
        $url = '/api/v1/items/'.$id.'/renderer-preset';
        $client->request('GET', '/api/v1/csrf');
        $token = json_decode($client->getResponse()->getContent(), true)['token'];
        $settings = ['width' => 375, 'zoom' => 75, 'rootSize' => 20, 'bodyFont' => 'system-serif', 'headingFont' => 'system-mono', 'textClass' => 'text-lg', 'scripts' => false, 'props' => (object) ['message' => 'Saved sample'], 'themeReleaseId' => null, 'colorScheme'=>'dark', 'surface'=>'base-200', 'deviceFrame'=>true];
        $client->jsonRequest('PUT', $url, $settings);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('PUT', $url, $settings, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $client->request('GET', $url);
        self::assertJsonStringEqualsJsonString(json_encode($settings), $client->getResponse()->getContent());
        $client->request('GET', '/api/v1/items/'.$id.'/draft');
        self::assertSame(1, json_decode($client->getResponse()->getContent(), true)['revision']);
        $this->signIn($client, $this->account());
        $client->request('GET', $url);
        self::assertSame(768, json_decode($client->getResponse()->getContent(), true)['width']);
        $this->signIn($client, $owner);
        $client->request('GET', $url);
        self::assertSame(375, json_decode($client->getResponse()->getContent(), true)['width']);
        $client->jsonRequest('PUT', $url, [...$settings, 'rootSize' => 900, 'bodyFont' => 'url(evil)'], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
    }
}
