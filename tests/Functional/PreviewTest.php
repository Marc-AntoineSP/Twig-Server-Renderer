<?php

namespace App\Tests\Functional;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Entity\PreviewJob;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Uid\Ulid;

final class PreviewTest extends DatabaseTestCase
{
    public function testRealPreviewJobRunsWorkersAndDocumentIsOwnerOnly(): void
    {
        $client = self::createClient();
        $user = $this->account();
        $this->signIn($client, $user);
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('tests/preview-'.strtolower((string) new Ulid()), 'component', 'Preview'), $user);
        $client->request('GET', '/api/v1/csrf');
        $token = json_decode($client->getResponse()->getContent(), true)['token'];
        $client->jsonRequest('POST', '/api/v1/preview-jobs', ['itemId' => $draft->getItem()->getId(), 'sequence' => 7, 'channel' => str_repeat('a', 32)], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(202, $client->getResponse()->getContent());
        $job = json_decode($client->getResponse()->getContent(), true);
        $client->request('GET', '/api/v1/preview-jobs/'.$job['id']);
        $status = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('succeeded', $status['status'], json_encode($status));
        $client->request('GET', '/api/v1/preview-jobs/'.$job['id'].'/document');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        $document = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(7, $document['sequence']);
        self::assertStringContainsString('Your component is ready.', $document['html']);
        self::assertStringContainsString('Content-Security-Policy', $document['html']);
        $this->signIn($client, $this->account('ROLE_ADMIN'));
        $client->request('GET', '/api/v1/preview-jobs/'.$job['id'].'/document');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSustainedEditingRetiresHistoryAndPreservesPendingJobs():void
    {
        $client=self::createClient();$user=$this->account();$this->signIn($client,$user);$em=self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        for($i=0;$i<60;$i++)$em->persist(new PreviewJob($user,$i,str_repeat('a',64),new \stdClass()));$em->flush();$em->getConnection()->executeStatement("UPDATE preview_job SET status='failed' WHERE owner_id=?",[$user->getId()]);
        $pending=[];for($i=0;$i<2;$i++){$job=new PreviewJob($user,61+$i,str_repeat('b',64),new \stdClass());$pending[]=$job->getId();$em->persist($job);}$em->flush();
        $draft=self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('tests/history-'.strtolower((string)new Ulid()),'component','Retained preview history'),$user);$client->request('GET','/api/v1/csrf');$csrf=json_decode($client->getResponse()->getContent())->token;
        $client->jsonRequest('POST','/api/v1/preview-jobs',['itemId'=>$draft->getItem()->getId(),'sequence'=>63,'channel'=>str_repeat('c',32)],['HTTP_X_CSRF_TOKEN'=>$csrf]);self::assertResponseStatusCodeSame(202,$client->getResponse()->getContent());$id=json_decode($client->getResponse()->getContent())->id;
        $client->request('GET','/api/v1/preview-jobs/'.$id.'/document');self::assertResponseIsSuccessful();$connection=self::getContainer()->get(\Doctrine\DBAL\Connection::class);self::assertSame(60,(int)$connection->fetchOne('SELECT COUNT(*) FROM preview_job WHERE owner_id=? AND expires_at>CURRENT_TIMESTAMP',[$user->getId()]));self::assertSame(2,(int)$connection->fetchOne("SELECT COUNT(*) FROM preview_job WHERE owner_id=? AND status='queued' AND expires_at>CURRENT_TIMESTAMP",[$user->getId()]));
    }
}
