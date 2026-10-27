<?php

namespace App\Tests\Functional;

use App\Entity\ThemeRelease;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class ThemeTest extends DatabaseTestCase
{
    public function testThemePersistenceConflictsPublicationAndExports(): void
    {
        $client = self::createClient();
        $owner = $this->account();
        $this->signIn($client, $owner);
        $document = self::getContainer()->get(ThemeFactory::class)->create('themes/item-'.strtolower((string) new Ulid()));
        $client->request('GET', '/api/v1/csrf');
        $headers = ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
        $client->jsonRequest('POST', '/api/v1/themes', ['document' => $document]);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/api/v1/themes', ['document' => $document], $headers);
        self::assertResponseStatusCodeSame(201, $client->getResponse()->getContent());
        $id = json_decode($client->getResponse()->getContent())->id;
        $document->colors->primary = '#123456';
        $document->typography->bodyFontId = 'system-serif';
        $document->typography->textClass = 'text-lg';
        $document->geometry->radiusBoxRem = 2;
        $client->jsonRequest('PUT', '/api/v1/themes/'.$id, ['document' => $document, 'expectedRevision' => 1], $headers);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $client->jsonRequest('PUT', '/api/v1/themes/'.$id, ['document' => $document, 'expectedRevision' => 1], $headers);
        self::assertResponseStatusCodeSame(412);
        $client->request('GET', '/api/v1/themes/'.$id);
        self::assertJsonStringEqualsJsonString(json_encode($document), json_encode(json_decode($client->getResponse()->getContent())->document));
        $client->jsonRequest('POST', '/api/v1/themes/'.$id.'/releases', ['expectedRevision' => 2], $headers);
        self::assertResponseStatusCodeSame(201, $client->getResponse()->getContent());
        $releaseId = json_decode($client->getResponse()->getContent())->id;
        $client->request('GET', '/theme-releases/'.$releaseId.'/export/preview.css');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('--color-primary:#123456', $client->getResponse()->getContent());
        self::assertStringContainsString('Georgia', $client->getResponse()->getContent());
        $client->jsonRequest('POST', '/api/v1/themes/'.$id.'/releases', ['expectedRevision' => 2], $headers);
        self::assertResponseStatusCodeSame(409);
        $document->colors->primary = '#ABCDEF';
        $client->jsonRequest('PUT', '/api/v1/themes/'.$id, ['document' => $document, 'expectedRevision' => 2], $headers);
        self::assertResponseIsSuccessful();
        $release = self::getContainer()->get(EntityManagerInterface::class)->find(ThemeRelease::class, $releaseId);
        self::assertSame('#123456', $release->getDocument()->colors->primary);
        $client->request('GET', '/themes/'.$id.'/export/bundle.zip');
        self::assertResponseIsSuccessful();
        $path = tempnam(sys_get_temp_dir(), 'theme-test-');
        try {
            file_put_contents($path, $client->getResponse()->getContent());
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertSame('#ABCDEF', json_decode($zip->getFromName('meta/theme.json'))->colors->primary);
            self::assertNotFalse($zip->getFromName('assets/styles/catalogue/'.$document->name.'/app.css'));
            $zip->close();
        } finally { unlink($path); }
        $client->request('GET', '/themes/'.$id.'/edit');
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        self::assertSelectorExists('iframe[sandbox="allow-scripts"]');
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->jsonRequest('POST', '/api/v1/themes', ['document' => $document], $headers);
        self::assertResponseStatusCodeSame(403);
    }
}
