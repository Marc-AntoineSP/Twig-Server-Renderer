<?php
namespace App\Tests\Functional;
use App\Tests\DatabaseTestCase;
use App\Repository\CatalogueRepository;
final class JsonRequestTest extends DatabaseTestCase
{
    public function testDuplicateRequestKeysCannotSilentlyChooseAnotherPackageIdentity():void
    {
        $client=self::createClient();$this->signIn($client,$this->account());$client->request('GET','/api/v1/csrf');$token=json_decode($client->getResponse()->getContent())->token;
        $client->request('POST','/api/v1/items',server:['CONTENT_TYPE'=>'application/json','HTTP_X_CSRF_TOKEN'=>$token],content:'{"name":"duplicates/first","name":"duplicates/second","kind":"component","title":"Ambiguous"}');
        self::assertResponseStatusCodeSame(400);self::assertSame('duplicate_json_key',json_decode($client->getResponse()->getContent())->code);
        self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named('duplicates/first'));
        self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named('duplicates/second'));
    }
}
