<?php
namespace App\Tests\Functional;

use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Entity\Release;
use App\Packaging\{PublishInput, ReleasePublisher};
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class CompositionHistoryTest extends DatabaseTestCase
{
    private array $headers;
    private function fixture(): array
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner);
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('history/item-'.strtolower((string) new Ulid()), 'layout', 'Original title'), $owner);
        $client->request('GET', '/api/v1/csrf'); $this->headers = ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
        $url = '/api/v1/items/'.$draft->getItem()->getId().'/composition';
        $client->request('GET', $url); self::assertResponseIsSuccessful();
        return [$client, $url, json_decode($client->getResponse()->getContent())->document];
    }
    private function save($client, string $url, object $document, int $revision, string $title = 'Original title'): object
    {
        $client->jsonRequest('PUT', $url, ['document' => $document, 'title' => $title, 'expectedRevision' => $revision], $this->headers);
        self::assertResponseIsSuccessful($client->getResponse()->getContent()); return json_decode($client->getResponse()->getContent());
    }
    private function records(string $url): array
    {
        $id = explode('/', $url)[4];
        return self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT * FROM composition_revision WHERE item_id=? ORDER BY revision', [$id]);
    }

    public function testHistoryRestoresAsNewHeadWithMetadataAndKeepsSnapshotsImmutable(): void
    {
        [$client, $url, $document] = $this->fixture();
        $client->request('GET', $url.'/history'); $empty = json_decode($client->getResponse()->getContent());
        self::assertSame([], $empty->history); self::assertNotEmpty($empty->warnings);
        $document->root->children[] = (object) ['type' => 'text', 'value' => 'First saved text'];
        $saved = $this->save($client, $url, $document, 1, 'Second title');
        $saved->document->root->children[0]->value = 'Later text';
        $this->save($client, $url, $saved->document, 2, 'Third title');
        $before = $this->records($url);
        $client->request('GET', $url.'/history/2?expectedRevision=3'); self::assertResponseIsSuccessful();
        $detail = json_decode($client->getResponse()->getContent());
        self::assertSame(1, $detail->counts->properties); self::assertSame('Second title', $detail->title);
        self::assertSame('title', $detail->metadata[0]->field);
        self::assertStringContainsString('First saved text', $detail->differences[0]->after);
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 3, 'restoreRevision' => 2], $this->headers);
        self::assertResponseIsSuccessful(); $restored = json_decode($client->getResponse()->getContent());
        self::assertSame(4, $restored->revision); self::assertSame('Second title', $restored->title);
        self::assertSame('First saved text', $restored->document->root->children[0]->value);
        self::assertStringContainsString('First saved text', $restored->generatedTwig);
        self::assertSame($before, array_slice($this->records($url), 0, count($before)));
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 3, 'restoreRevision' => 1], $this->headers);
        self::assertResponseStatusCodeSame(412); self::assertCount(4, $this->records($url));
        $client->request('GET', $url.'/history/2?expectedRevision=3'); self::assertResponseStatusCodeSame(412);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testBoundedPagesMissingSnapshotsAndUnauthorizedRestoreFailWithoutWrites(): void
    {
        [$client, $url, $document] = $this->fixture();
        for ($revision = 1; $revision <= 52; ++$revision) $this->save($client, $url, $document, $revision, 'Title '.$revision);
        $client->request('GET', $url.'/history?limit=20'); $first = json_decode($client->getResponse()->getContent());
        self::assertCount(20, $first->history); self::assertSame(53, $first->history[0]->revision); self::assertSame(20, $first->nextOffset);
        self::assertNotNull($first->history[0]->actorId); self::assertObjectNotHasProperty('document', $first->history[0]);
        $client->request('GET', $url.'/history?offset=40&limit=20'); $last = json_decode($client->getResponse()->getContent());
        self::assertCount(13, $last->history); self::assertNull($last->nextOffset);
        foreach (['limit=51', 'offset=10001', 'limit=0', 'offset=-1'] as $query) {$client->request('GET', $url.'/history?'.$query); self::assertResponseStatusCodeSame(422);}
        $before = $this->records($url);
        $client->request('GET', $url.'/history/999?expectedRevision=53'); self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 53, 'restoreRevision' => 1]); self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 53, 'restoreRevision' => 999], $this->headers); self::assertResponseStatusCodeSame(404);
        $this->signIn($client, $this->account('ROLE_READER'));
        foreach (['/history', '/history/1?expectedRevision=53'] as $path) {$client->request('GET', $url.$path); self::assertResponseStatusCodeSame(403);}
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 53, 'restoreRevision' => 1], $this->headers); self::assertResponseStatusCodeSame(403);
        self::assertSame($before, $this->records($url));
        $client->getCookieJar()->clear(); $client->request('GET', $url.'/history'); self::assertResponseStatusCodeSame(401);
    }

    public function testDifferencesIncludeMovesPropsVersionsAndUnavailableReferencesCannotBeRestored(): void
    {
        [$client, $url, $document] = $this->fixture();
        $writer = self::getContainer()->get(CatalogueWriter::class);
        $leaf = $writer->create(new CreateItemInput('history/leaf-'.strtolower((string) new Ulid()), 'component', 'Leaf'), null);
        $publisher = self::getContainer()->get(ReleasePublisher::class);
        $one = $publisher->publish($leaf->getItem(), new PublishInput('1.0.0', 1));
        $publisher->publish($leaf->getItem(), new PublishInput('2.0.0', 1));
        $document->root->children = [(object) ['type' => 'reference', 'id' => 'n_'.str_repeat('a', 32), 'name' => $leaf->getItem()->getName(), 'version' => '1.0.0', 'props' => (object) ['message' => 'Before']], (object) ['type' => 'text', 'id' => 'n_'.str_repeat('b', 32), 'value' => 'Move me']];
        $saved = $this->save($client, $url, $document, 1);
        $next = $saved->document; $next->root->children = array_reverse($next->root->children);
        $next->root->children[1]->version = '2.0.0'; $next->root->children[1]->props->message = 'After';
        $this->save($client, $url, $next, 2);
        $client->request('GET', $url.'/history/2?expectedRevision=3'); self::assertResponseIsSuccessful();
        $diff = json_decode($client->getResponse()->getContent());
        self::assertSame(2, $diff->counts->moved); self::assertSame(1, $diff->counts->references); self::assertSame(1, $diff->counts->properties);
        self::assertContains('props.message', array_column($diff->differences, 'field'));
        $em = self::getContainer()->get(EntityManagerInterface::class); $em->remove($em->find(Release::class, $one->getId())); $em->flush();
        $client->request('GET', $url.'/history/2?expectedRevision=3'); self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', $url.'/restore', ['expectedRevision' => 3, 'restoreRevision' => 2], $this->headers); self::assertResponseStatusCodeSame(422);
        $client->request('GET', $url); self::assertSame(3, json_decode($client->getResponse()->getContent())->revision);
        self::assertCount(3, $this->records($url));
    }
}
