<?php

namespace App\Tests\Functional;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\DraftInput;
use App\Entity\PreviewJob;
use App\Packaging\PublishInput;
use App\Packaging\ReleasePublisher;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class CataloguePreviewTest extends DatabaseTestCase
{
    public function testReaderPreviewsExactPublishedExamplesWithoutSavingAndCannotSubmitSourceOrEditDrafts(): void
    {
        $client = self::createClient(); $editor = $this->account();
        $writer = self::getContainer()->get(CatalogueWriter::class);
        $draft = $writer->create(new CreateItemInput('tests/detail-'.strtolower((string) new Ulid()), 'component', 'Preview first'), $editor);
        $item = $draft->getItem();
        $old = self::getContainer()->get(ReleasePublisher::class)->publish($item, new PublishInput('1.0.0', 1));
        $manifest = $draft->getManifestData(); $manifest->defaults->message = 'Newer saved draft';
        $sources = $draft->getSourceMap();
        $writer->save($item, 1, new DraftInput($manifest, $sources, 'Preview first'), $editor);
        self::getContainer()->get(ReleasePublisher::class)->publish($item, new PublishInput('2.0.0', 2));
        $reader = $this->account('ROLE_READER'); $this->signIn($client, $reader);
        $connection = self::getContainer()->get(Connection::class);
        $before = $connection->fetchAllAssociative('SELECT * FROM renderer_preset');
        $client->request('GET', '/catalogue/'.$item->getId(), ['release' => $old->getId()]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-catalogue-preview-target="identity"]', 'Published release 1.0.0');
        self::assertSelectorNotExists('a[href$="/edit"]');
        self::assertSelectorExists('#releases a[href$="/source.zip"]');
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM preview_job WHERE owner_id=?', [$reader->getId()]));
        $client->request('GET', '/api/v1/csrf'); $csrf = json_decode($client->getResponse()->getContent())->token;
        $url = '/api/v1/catalogue-previews/'.$item->getId();
        $body = ['sequence' => 1, 'channel' => str_repeat('a', 32), 'releaseId' => $old->getId(), 'example' => 0];
        $client->jsonRequest('POST', $url, $body); self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(202, $client->getResponse()->getContent());
        $job = json_decode($client->getResponse()->getContent());
        self::assertSame($old->getId(), $job->selection->releaseId);
        self::assertNull($job->selection->draftRevision);
        $client->request('GET', '/api/v1/preview-jobs/'.$job->id.'/document');
        self::assertResponseIsSuccessful();
        $document = json_decode($client->getResponse()->getContent());
        self::assertSame($old->getId(), $document->selection->releaseId);
        self::assertStringContainsString('Content-Security-Policy', $document->html);
        self::assertStringNotContainsString('Newer saved draft', $document->html);
        self::assertSame($before, self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT * FROM renderer_preset'));
        $client->jsonRequest('POST', $url, [...$body, 'sourceMap' => new \stdClass()], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', $url, ['sequence' => 2, 'channel' => str_repeat('b', 32), 'draftRevision' => 2], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/api/v1/preview-jobs', ['itemId' => $item->getId(), 'sequence' => 3, 'channel' => str_repeat('c', 32)], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/catalogue/'.$item->getId().'/edit'); self::assertResponseStatusCodeSame(403);
        $this->signIn($client, $this->account());
        $client->request('GET', '/api/v1/preview-jobs/'.$job->id.'/document'); self::assertResponseStatusCodeSame(404);
    }

    public function testDraftRevisionsAreCapturedAndOtherReleasesExamplesAndExpiredOutputsAreRejected(): void
    {
        $client = self::createClient(); $editor = $this->account(); $this->signIn($client, $editor);
        $writer = self::getContainer()->get(CatalogueWriter::class);
        $draft = $writer->create(new CreateItemInput('tests/draft-'.strtolower((string) new Ulid()), 'component', 'Draft only'), $editor);
        $other = $writer->create(new CreateItemInput('tests/other-'.strtolower((string) new Ulid()), 'component', 'Other'), $editor);
        $release = self::getContainer()->get(ReleasePublisher::class)->publish($other->getItem(), new PublishInput('1.0.0', 1));
        $client->request('GET', '/api/v1/csrf'); $csrf = json_decode($client->getResponse()->getContent())->token;
        $url = '/api/v1/catalogue-previews/'.$draft->getItem()->getId();
        $body = ['sequence' => 1, 'channel' => str_repeat('a', 32), 'draftRevision' => 1];
        $client->jsonRequest('POST', $url, [...$body, 'releaseId' => $release->getId()], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', $url, ['sequence' => 1, 'channel' => str_repeat('a', 32), 'releaseId' => $release->getId()], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('POST', $url, [...$body, 'example' => 19], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(202);
        $job = json_decode($client->getResponse()->getContent()); self::assertSame(1, $job->selection->draftRevision);
        $writer = self::getContainer()->get(CatalogueWriter::class); $item = self::getContainer()->get(EntityManagerInterface::class)->find(\App\Entity\CatalogueItem::class, $draft->getItem()->getId());
        $writer->save($item, 1, new DraftInput($draft->getManifestData(), $draft->getSourceMap(), 'New head'), self::getContainer()->get(EntityManagerInterface::class)->find(\App\Entity\User::class, $editor->getId()));
        $client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(412);
        self::getContainer()->get(Connection::class)->executeStatement("UPDATE preview_job SET expires_at=CURRENT_TIMESTAMP-INTERVAL '1 second' WHERE id=?", [$job->id]);
        $client->request('GET', '/api/v1/preview-jobs/'.$job->id); self::assertSame('expired', json_decode($client->getResponse()->getContent())->status);
        $client->request('GET', '/api/v1/preview-jobs/'.$job->id.'/document'); self::assertResponseStatusCodeSame(409);
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->request('GET', '/catalogue/'.$item->getId()); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main', 'No published preview'); self::assertSelectorNotExists('[data-controller="catalogue-preview"]');
    }

    public function testCacheKeysSeparateRevisionsPropsAndOwnersButNotChannels(): void
    {
        $client = self::createClient(); $editor = $this->account(); $this->signIn($client, $editor);
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('tests/cache-'.strtolower((string) new Ulid()), 'component', 'Cache'), $editor);
        $client->request('GET', '/api/v1/csrf'); $csrf = json_decode($client->getResponse()->getContent())->token;
        $url = '/api/v1/catalogue-previews/'.$draft->getItem()->getId(); $body = ['sequence' => 1, 'channel' => str_repeat('a', 32), 'draftRevision' => 1];
        $client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(202); $first = json_decode($client->getResponse()->getContent());
        $client->jsonRequest('POST', $url, [...$body, 'sequence' => 2, 'channel' => str_repeat('b', 32)], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(202); $second = json_decode($client->getResponse()->getContent());
        $em = self::getContainer()->get(EntityManagerInterface::class); $one = $em->find(PreviewJob::class, $first->id); $two = $em->find(PreviewJob::class, $second->id);
        self::assertSame($one->getInputHash(), $two->getInputHash()); self::assertNotSame($one->getId(), $two->getId());
        $client->request('GET', '/api/v1/preview-jobs/'.$second->id.'/document'); self::assertResponseIsSuccessful();
        $html = json_decode($client->getResponse()->getContent())->html; self::assertStringContainsString('const channel="'.str_repeat('b', 32).'"', $html); self::assertStringNotContainsString('const channel="'.str_repeat('a', 32).'"', $html);
        $props = $draft->getManifestData()->defaults; $props->message = 'Different properties';
        $client->jsonRequest('POST', $url, [...$body, 'sequence' => 3, 'props' => $props], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(202); $third = json_decode($client->getResponse()->getContent());
        $em = self::getContainer()->get(EntityManagerInterface::class); self::assertNotSame($one->getInputHash(), $em->find(PreviewJob::class, $third->id)->getInputHash());
        $other = $this->account(); $this->signIn($client, $other);
        $client->request('GET', '/api/v1/csrf'); $otherCsrf = json_decode($client->getResponse()->getContent())->token;
        $client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $otherCsrf]); self::assertResponseStatusCodeSame(202); $otherJob = json_decode($client->getResponse()->getContent());
        $em = self::getContainer()->get(EntityManagerInterface::class); self::assertNotSame($one->getInputHash(), $em->find(PreviewJob::class, $otherJob->id)->getInputHash());
        $this->signIn($client, $editor);
        $client->request('GET', '/api/v1/csrf'); $csrf = json_decode($client->getResponse()->getContent())->token;
        $em = self::getContainer()->get(EntityManagerInterface::class); $item = $em->find(\App\Entity\CatalogueItem::class, $draft->getItem()->getId());
        self::getContainer()->get(CatalogueWriter::class)->save($item, 1, new DraftInput($draft->getManifestData(), $draft->getSourceMap(), 'Cache'), $em->find(\App\Entity\User::class, $editor->getId()));
        $client->jsonRequest('POST', $url, [...$body, 'draftRevision' => 2], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(202); $newRevision = json_decode($client->getResponse()->getContent());
        $em = self::getContainer()->get(EntityManagerInterface::class); self::assertNotSame($one->getInputHash(), $em->find(PreviewJob::class, $newRevision->id)->getInputHash());

    }
}
