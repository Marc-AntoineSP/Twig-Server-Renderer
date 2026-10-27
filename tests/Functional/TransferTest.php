<?php
namespace App\Tests\Functional;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Packaging\TransferImporter;
use App\Repository\CatalogueRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CollectionBuilder;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\ZipBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;
final class TransferTest extends DatabaseTestCase
{
    private array $temps=[];
    protected function tearDown():void{foreach($this->temps as $p)if(is_file($p))unlink($p);parent::tearDown();}
    private function package(bool $broken=false):array
    {
        [$m,$s]=self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput('transfer/item-'.strtolower((string)new Ulid()),'component','Transferred'));
        if($broken)$s->{$m->entry}='{% include "base.html.twig" %}';
        $m=self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);$bytes=ZipBuilder::bytes(['manifest.json'=>self::getContainer()->get(CanonicalJson::class)->encode($m,true)."\n",...(array)$s]);
        return ['manifest'=>$m,'sources'=>$s,'archiveBytes'=>$bytes,'sha256'=>hash('sha256',$bytes)];
    }
    private function file(string $bytes):string{$p=tempnam(sys_get_temp_dir(),'transfer-test-');file_put_contents($p,$bytes);$this->temps[]=$p;return $p;}
    public function testReviewedImportPersistsReleasesPreservesDraftsAndExportsPrivately():void
    {
        $client=self::createClient();$owner=$this->account();$this->signIn($client,$owner);$p=$this->package();$name=$p['manifest']->name;$file=$this->file($p['archiveBytes']);
        $client->request('GET','/api/v1/csrf');$headers=['HTTP_X_CSRF_TOKEN'=>json_decode($client->getResponse()->getContent())->token];
        $upload=static fn()=>new UploadedFile($file,'source.zip','application/zip',null,true);
        $client->request('POST','/api/v1/imports',['mode'=>'validate'],['file'=>$upload()]);self::assertResponseStatusCodeSame(403);
        $client->request('POST','/api/v1/imports',['mode'=>'dry-run'],['file'=>$upload()],$headers);self::assertResponseIsSuccessful($client->getResponse()->getContent());$plan=json_decode($client->getResponse()->getContent());self::assertFalse($plan->applied);
        self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named($name));
        $client->request('POST','/api/v1/imports',['mode'=>'apply'],['file'=>$upload()],$headers);self::assertResponseStatusCodeSame(428);
        $client->request('POST','/api/v1/imports',['mode'=>'apply','expectedPlan'=>$plan->planHash],['file'=>$upload()],$headers);self::assertResponseIsSuccessful($client->getResponse()->getContent());$result=json_decode($client->getResponse()->getContent())->result;self::assertSame(1,$result->applied);$id=$result->items->$name;
        $client->request('GET','/api/v1/items/'.$id.'/draft');$draft=json_decode($client->getResponse()->getContent());$draft->sourceMap->{$draft->manifestData->entry}='<h2>Local draft kept</h2>';
        $client->jsonRequest('PUT','/api/v1/items/'.$id.'/draft',['manifestData'=>$draft->manifestData,'sourceMap'=>$draft->sourceMap,'title'=>'Local title'],[...$headers,'HTTP_IF_MATCH'=>'"1"']);self::assertResponseIsSuccessful();
        $client->request('POST','/api/v1/imports',['mode'=>'validate'],['file'=>$upload()],$headers);self::assertResponseIsSuccessful();$again=json_decode($client->getResponse()->getContent());self::assertSame('preserve-existing',$again->plan->packages[0]->draft);
        $client->request('POST','/api/v1/imports',['mode'=>'apply','expectedPlan'=>$again->planHash],['file'=>$upload()],$headers);self::assertResponseIsSuccessful();self::assertSame(0,json_decode($client->getResponse()->getContent())->result->applied);
        $client->request('GET','/api/v1/items/'.$id.'/draft');self::assertSame('Local title',json_decode($client->getResponse()->getContent())->title);self::assertSame(2,json_decode($client->getResponse()->getContent())->revision);
        $em=self::getContainer()->get(EntityManagerInterface::class);$item=$em->find(CatalogueItem::class,$id);$release=$em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$p['manifest']->version]);
        $client->jsonRequest('POST','/api/v1/exports',['releaseId'=>$release->getId(),'format'=>'static'],$headers);self::assertResponseStatusCodeSame(201,$client->getResponse()->getContent());$export=json_decode($client->getResponse()->getContent());
        $client->request('GET',$export->downloadUrl);self::assertResponseIsSuccessful();$bytes=$client->getInternalResponse()->getContent();self::assertSame($export->sha256,hash('sha256',$bytes));self::assertStringContainsString('private',$client->getResponse()->headers->get('Cache-Control'));
        $zip=new \ZipArchive();self::assertTrue($zip->open($this->file($bytes)));$html=$zip->getFromName('index.html');$zip->close();self::assertStringContainsString('Your component is ready.',$html);self::assertStringNotContainsString('Local draft kept',$html);
        $this->signIn($client,$this->account());$client->request('GET',$export->downloadUrl);self::assertResponseStatusCodeSame(404);
    }
    public function testCollectionBuildFailureAndStalePlansLeaveNoPartialCatalogueWrites():void
    {
        self::bootKernel();$owner=$this->account();$good=$this->package();$bad=$this->package(true);$packages=[$good['manifest']->name=>$good,$bad['manifest']->name=>$bad];$roots=array_map(static fn($p)=>(object)['name'=>$p['manifest']->name,'version'=>$p['manifest']->version],array_values($packages));
        $bytes=self::getContainer()->get(CollectionBuilder::class)->bytes($packages,$roots);$importer=self::getContainer()->get(TransferImporter::class);
        try{$importer->prepare($this->file($bytes),'zip');self::fail('Invalid template import accepted.');}catch(\Styleguide\Contracts\CatalogueException $e){self::assertSame(422,$e->getStatusCode());}
        self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named($good['manifest']->name));self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named($bad['manifest']->name));
        $prepared=$importer->prepare($this->file($good['archiveBytes']),'zip');
        try{$importer->apply($prepared,$owner,str_repeat('0',64));self::fail('Stale plan accepted.');}catch(\Styleguide\Contracts\CatalogueException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertNull(self::getContainer()->get(CatalogueRepository::class)->named($good['manifest']->name));
    }
}
