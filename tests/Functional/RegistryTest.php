<?php
namespace App\Tests\Functional;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Entity\ReadToken;
use App\Entity\User;
use App\Packaging\PublishInput;
use App\Packaging\ReleasePublisher;
use App\Registry\Tokens;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
final class RegistryTest extends DatabaseTestCase
{
    public function testScopedBearerDownloadsCannotCreateSessionsAndAuthPrecedesEtags():void
    {
        $client=self::createClient();$admin=$this->account('ROLE_ADMIN');
        $draft=self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('registry/item-'.strtolower((string)new Ulid()),'component','Registry'),$admin);
        $release=self::getContainer()->get(ReleasePublisher::class)->publish($draft->getItem(),new PublishInput('1.0.0',1));
        [$token,$plain]=self::getContainer()->get(Tokens::class)->issue($admin,'Tests',['registry/*']);$id=$token->getId();$name=$draft->getItem()->getName();$url='/api/v1/registry/'.$name;
        $client->request('GET',$url);self::assertResponseStatusCodeSame(401);
        $headers=['HTTP_AUTHORIZATION'=>'Bearer '.$plain];$client->request('GET',$url,server:$headers);self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        self::assertStringNotContainsString($plain,$client->getResponse()->getContent());
        self::assertSame($release->getArchiveHash(),json_decode($client->getResponse()->getContent())->versions[0]->archiveSha256);
        $etag=$client->getResponse()->headers->get('ETag');
        $client->request('GET',$url,server:[...$headers,'HTTP_IF_NONE_MATCH'=>$etag]);self::assertResponseStatusCodeSame(304);
        $client->request('GET',$url.'/1.0.0/manifest',server:$headers);self::assertResponseIsSuccessful();self::assertSame($name,json_decode($client->getResponse()->getContent())->name);
        $client->request('GET',$url.'/1.0.0/archive',server:$headers);self::assertResponseIsSuccessful();
        self::assertSame($release->getArchiveHash(),hash('sha256',$client->getInternalResponse()->getContent()));
        self::assertStringContainsString('private',$client->getResponse()->headers->get('Cache-Control'));
        $client->request('GET','/api/v1/registry/other/private',server:[...$headers,'HTTP_IF_NONE_MATCH'=>$etag]);self::assertResponseStatusCodeSame(403);
        $client->request('GET','/api/v1/csrf',server:$headers);self::assertResponseStatusCodeSame(401);
        $token=self::getContainer()->get(EntityManagerInterface::class)->find(ReadToken::class,$id);self::getContainer()->get(Tokens::class)->revoke($token);
        $client->request('GET',$url,server:[...$headers,'HTTP_IF_NONE_MATCH'=>$etag]);self::assertResponseStatusCodeSame(401);
    }
    public function testExpiredAndDisabledOwnerTokensFailAndOnlyAdminsManageThem():void
    {
        $client=self::createClient();$admin=$this->account('ROLE_ADMIN');$plain='sgt_'.bin2hex(random_bytes(32));
        $em=self::getContainer()->get(EntityManagerInterface::class);$expired=new ReadToken($admin,'Expired',hash('sha256',$plain),['*'],new \DateTimeImmutable('-1 day'));$em->persist($expired);$em->flush();
        $client->request('GET','/api/v1/registry/any/package',server:['HTTP_AUTHORIZATION'=>'Bearer '.$plain]);self::assertResponseStatusCodeSame(401);
        $em=self::getContainer()->get(EntityManagerInterface::class);$owner=$em->find(User::class,$admin->getId());[$token,$active]=self::getContainer()->get(Tokens::class)->issue($owner,'Active',['*']);$owner->disable();$em->flush();
        $client->request('GET','/api/v1/registry/any/package',server:['HTTP_AUTHORIZATION'=>'Bearer '.$active]);self::assertResponseStatusCodeSame(401);
        $this->signIn($client,$this->account());$client->request('GET','/registry-tokens');self::assertResponseStatusCodeSame(403);
        $this->signIn($client,$this->account('ROLE_ADMIN'));$client->request('POST','/registry-tokens',['label'=>'Bad','scopes'=>'*']);self::assertResponseStatusCodeSame(403);
        $client->request('GET','/registry-tokens');self::assertResponseIsSuccessful();self::assertSelectorNotExists('[data-read-token]');
    }
}
