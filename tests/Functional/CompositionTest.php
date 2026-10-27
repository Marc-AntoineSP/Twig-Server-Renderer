<?php
namespace App\Tests\Functional;
use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Packaging\PublishInput;
use App\Packaging\ReleasePublisher;
use App\Rendering\DependencyResolver;
use App\Rendering\PreviewRenderer;
use App\Rendering\ReleaseSourceReader;
use App\Composition\CompositionCompiler;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Uid\Ulid;
final class CompositionTest extends DatabaseTestCase
{
    private function packageName(string $kind): string { return 'compose/'.$kind.'-'.strtolower((string) new Ulid()); }
    public function testUnknownSelfAndConflictingReferencesFailBeforeSaving(): void
    {
        self::bootKernel(); $user=$this->account(); $writer=self::getContainer()->get(CatalogueWriter::class);
        $leaf=$writer->create(new CreateItemInput($this->packageName('leaf'),'component','Leaf'),$user);
        $publisher=self::getContainer()->get(ReleasePublisher::class);
        $publisher->publish($leaf->getItem(),new PublishInput('1.0.0',1));
        $publisher->publish($leaf->getItem(),new PublishInput('2.0.0',1));
        $block=$writer->create(new CreateItemInput($this->packageName('block'),'block','Block'),$user);
        $reference=static fn($name,$version,$props)=>(object)['type'=>'reference','name'=>$name,'version'=>$version,'props'=>$props];
        $cases=[
            [$reference('missing/component','1.0.0',(object)[]),422],
            [$reference($block->getItem()->getName(),'1.0.0',(object)[]),422],
            [$reference($leaf->getItem()->getName(),'1.0.0',(object)['message'=>500]),422],
            [(object)['type'=>'container','tag'=>'div','classes'=>[],'children'=>[$reference($leaf->getItem()->getName(),'1.0.0',(object)['message'=>'One']),$reference($leaf->getItem()->getName(),'2.0.0',(object)['message'=>'Two'])]],409],
        ];
        foreach($cases as [$root,$status]) {
            try { self::getContainer()->get(CompositionCompiler::class)->materialize($block->getManifestData(),$block->getSourceMap(),(object)['schemaVersion'=>1,'root'=>$root]); self::fail('Invalid reference accepted.'); }
            catch(\Styleguide\Contracts\CatalogueException $e) { self::assertSame($status,$e->getStatusCode()); }
        }
        self::assertSame(1,$block->getRevision());
    }
    public function testNestedCompositionPersistsDistinctPropsAndLiteralTwigText(): void
    {
        $client = self::createClient(); $user = $this->account(); $this->signIn($client,$user);
        $writer = self::getContainer()->get(CatalogueWriter::class);
        $leaf = $writer->create(new CreateItemInput($this->packageName('leaf'), 'component', 'Leaf'),$user);
        $leafRelease = self::getContainer()->get(ReleasePublisher::class)->publish($leaf->getItem(),new PublishInput('1.0.0',1));
        $block = $writer->create(new CreateItemInput($this->packageName('block'),'block','Block'),$user); $id = $block->getItem()->getId();
        $tree = (object) ['schemaVersion'=>1,'root'=>(object)['type'=>'container','tag'=>'section','classes'=>['grid','gap-4','md:grid-cols-2'],'children'=>[
            (object)['type'=>'reference','name'=>$leaf->getItem()->getName(),'version'=>'1.0.0','props'=>(object)['message'=>'First instance']],
            (object)['type'=>'reference','name'=>$leaf->getItem()->getName(),'version'=>'1.0.0','props'=>(object)['message'=>'Second instance']],
            (object)['type'=>'text','value'=>"{{ 7*7 }} {% include 'base.html.twig' %} <script>x</script> #{bad} \\ '"],
        ]]];
        $client->request('GET','/api/v1/csrf');$headers=['HTTP_X_CSRF_TOKEN'=>json_decode($client->getResponse()->getContent())->token];
        $url='/api/v1/items/'.$id.'/composition';$input=['document'=>$tree,'expectedRevision'=>1];
        $client->jsonRequest('PUT',$url,$input);self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST',$url,$input,$headers);self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $client->jsonRequest('PUT',$url,$input,$headers);self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $client->jsonRequest('PUT',$url,$input,$headers);self::assertResponseStatusCodeSame(412);
        $client->request('GET','/api/v1/items/'.$id.'/draft'); $saved=json_decode($client->getResponse()->getContent());
        self::assertEquals($tree,json_decode($saved->sourceMap->{'meta/composition.json'}));
        $writer=self::getContainer()->get(CatalogueWriter::class);$block=$writer->draft(self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->find(\App\Entity\CatalogueItem::class,$id));
        $release=self::getContainer()->get(ReleasePublisher::class)->publish($block->getItem(),new PublishInput('1.0.0',2));
        $layout=$writer->create(new CreateItemInput($this->packageName('layout'),'layout','Layout'),$this->account());
        $layoutTree=(object)['schemaVersion'=>1,'root'=>(object)['type'=>'reference','name'=>$block->getItem()->getName(),'version'=>'1.0.0','props'=>(object)[]]];
        [$m,$s]=self::getContainer()->get(CompositionCompiler::class)->materialize($layout->getManifestData(),$layout->getSourceMap(),$layoutTree);
        $closure=self::getContainer()->get(DependencyResolver::class)->resolve($m,$s);
        $render=self::getContainer()->get(PreviewRenderer::class)->render([...$closure,'props'=>(object)[],'channel'=>str_repeat('a',32)]);
        self::assertTrue($render['ok'],json_encode($render));
        self::assertLessThan(strpos($render['html'],'Second instance'),strpos($render['html'],'First instance'));
        self::assertStringContainsString('{{ 7*7 }}',$render['html']);
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;',$render['html']);
        self::assertStringContainsString('md\\:grid-cols-2',$render['css']);
        $client->request('GET','/catalogue/'.$id.'/compose');self::assertResponseIsSuccessful();
        $crawler=$client->getCrawler();$token=$crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST','/catalogue/'.$id.'/convert-source',['_token'=>$token,'name'=>$this->packageName('converted')]);self::assertResponseStatusCodeSame(303);
        $client->followRedirect();self::assertSelectorExists('[data-controller~="editor"]');
        $client->request('GET','/api/v1/items/'.$id.'/draft');$original=json_decode($client->getResponse()->getContent());
        self::assertObjectHasProperty('meta/composition.json',$original->sourceMap);
        $original->sourceMap->{$original->manifestData->entry}='Modified generated Twig';
        $client->jsonRequest('PUT','/api/v1/items/'.$id.'/draft',['title'=>'Bypass','manifestData'=>$original->manifestData,'sourceMap'=>$original->sourceMap],[...$headers,'HTTP_IF_MATCH'=>'"2"']);self::assertResponseStatusCodeSame(409);
    }
}
