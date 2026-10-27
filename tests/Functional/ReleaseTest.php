<?php
namespace App\Tests\Functional;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\DraftInput;
use App\Entity\Release;
use App\Rendering\ReleaseSourceReader;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
final class ReleaseTest extends DatabaseTestCase
{
    public function testPublicationValidatesRealSourceAndPreservesImmutableArchive(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner);
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('releases/item-'.strtolower((string) new Ulid()), 'component', 'Publication'), $owner);
        $id = $draft->getItem()->getId(); $entry = $draft->getManifestData()->entry;
        $client->request('GET', '/api/v1/csrf'); $headers = ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
        $url = '/api/v1/items/'.$id.'/releases'; $input = ['version' => '1.0.0', 'expectedRevision' => 1];
        $client->jsonRequest('POST', $url, $input); self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', $url, [...$input, 'expectedRevision' => 8], $headers); self::assertResponseStatusCodeSame(412);
        $client->jsonRequest('POST', $url, $input, $headers); self::assertResponseStatusCodeSame(201, $client->getResponse()->getContent());
        $releaseId = json_decode($client->getResponse()->getContent())->id;
        $release = self::getContainer()->get(EntityManagerInterface::class)->find(Release::class, $releaseId);
        $original = self::getContainer()->get(ReleaseSourceReader::class)->read($release);
        self::assertStringContainsString('{{ message }}', $original->$entry);
        self::assertObjectHasProperty('meta/LICENSE', $original);
        $client->jsonRequest('POST', $url, $input, $headers); self::assertResponseStatusCodeSame(409);
        $client->request('GET', '/api/v1/items/'.$id.'/draft'); $data = json_decode($client->getResponse()->getContent());
        $data->sourceMap->$entry = '{% include "base.html.twig" %}';
        $client->jsonRequest('PUT', '/api/v1/items/'.$id.'/draft', ['title' => 'Invalid publish', 'manifestData' => $data->manifestData, 'sourceMap' => $data->sourceMap], [...$headers, 'HTTP_IF_MATCH' => '"1"']);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('POST', $url, ['version' => '2.0.0', 'expectedRevision' => 2], $headers); self::assertResponseStatusCodeSame(422);
        self::assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Release::class)->count(['item' => $id]));
        $release = self::getContainer()->get(EntityManagerInterface::class)->find(Release::class, $releaseId);
        self::assertSame($original->$entry, self::getContainer()->get(ReleaseSourceReader::class)->read($release)->$entry);
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->jsonRequest('POST', $url, $input, $headers); self::assertResponseStatusCodeSame(403);
    }
}
