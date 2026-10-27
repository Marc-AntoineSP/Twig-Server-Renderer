<?php
namespace App\Tests\Integration;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Rendering\WorkerClient;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\PropsValidator;
use Styleguide\Contracts\SafeArchive;
use Styleguide\Contracts\StrictJson;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class PortableContractsTest extends KernelTestCase
{
    public function testStrictJsonRejectsDecodedDuplicateKeysAtEveryLevel():void
    {
        foreach(['{"a":1,"a":2}','{"a":1,"\\u0061":2}','[{"x":{},"x":[]}]'] as $json) {
            try{StrictJson::decode($json);self::fail('Duplicate key accepted.');}catch(CatalogueException $e){self::assertSame('duplicate_json_key',$e->errorCode);}
        }
        $expected=(object)['a'=>[(object)['a'=>'quotes " and { braces']]]; self::assertEquals($expected,StrictJson::decode(json_encode($expected,JSON_THROW_ON_ERROR)));
    }
    public function testThemeFixtureUsesTheSamePortableValidatorAndNormativeSchemas():void
    {
        self::bootKernel();$root=self::getContainer()->getParameter('kernel.project_dir');
        foreach(['package','composition','theme','lock'] as $name) self::assertSame(hash_file('sha256',$root.'/agent-kit/schemas/'.$name.'.schema.json'),hash_file('sha256',$root.'/packages/catalogue-contracts/schemas/'.$name.'.schema.json'));
        $directory=$root.'/agent-kit/examples/packages/ember/';$manifest=StrictJson::decode(file_get_contents($directory.'manifest.json'));$sources=new \stdClass();
        foreach($manifest->files as $file)$sources->{$file->path}=file_get_contents($directory.$file->path);
        $valid=self::getContainer()->get(PackageValidator::class)->validate($manifest,$sources);self::assertSame('theme',$valid->kind);
        $sources->{'assets/styles/catalogue/awui/ember/theme.fragment.css'}.='@plugin "bad";';
        $this->expectException(CatalogueException::class);self::getContainer()->get(PackageValidator::class)->validate($manifest,$sources,true);
    }
    public function testNestedControllerIdentityMatchesRealStimulusAndCompiles():void
    {
        self::bootKernel();[$m,$s]=self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput('contracts/nested','component','Nested'));
        $path='assets/controllers/catalogue/contracts/nested/list_item_controller.js';$identifier='catalogue--contracts--nested--list-item';
        $s->$path='import {Controller} from "@hotwired/stimulus";export default class extends Controller {connect(){this.element.dataset.ready="yes"}}';
        $m->controllers=[(object)['identifier'=>$identifier,'path'=>$path]];
        $m=self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);
        $result=self::getContainer()->get(WorkerClient::class)->build(['sources'=>$s,'controllers'=>$m->controllers,'safelist'=>[]]);
        self::assertTrue($result['ok'],json_encode($result));self::assertStringContainsString($identifier,$result['js']);
        $m->controllers=[];$this->expectException(CatalogueException::class);self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);
    }
    public function testDangerousUrlSchemesFailRegardlessOfPropertyName():void
    {
        self::bootKernel();$validator=self::getContainer()->get(PropsValidator::class);$schema=(object)['type'=>'object','additionalProperties'=>true];
        foreach(['javascript:alert(1)'," java\nscript:alert(1)",'data:text/html,evil','file:///etc/passwd','vbscript:evil'] as $url){
            try{$validator->validateValue((object)['destination'=>$url],$schema);self::fail('Unsafe URL accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());}
        }
    }
    public function testRealArchivesRejectTraversalSymlinksCaseCollisionsAndBombs():void
    {
        $reader=new SafeArchive();
        foreach(['../evil','/absolute','C:/drive','a\\b','a/./b','a/../b','a//b','CON.txt','symlink','collision','bomb'] as $name) {
            $path=tempnam(sys_get_temp_dir(),'bad-zip-');$zip=new \ZipArchive();$zip->open($path,\ZipArchive::OVERWRITE);
            if('symlink'===$name){$zip->addFromString('link','target');$zip->setExternalAttributesName('link',\ZipArchive::OPSYS_UNIX,0120777<<16);}
            elseif('collision'===$name){$zip->addFromString('same.txt','a');$zip->addFromString('SAME.txt','b');}
            elseif('bomb'===$name){$zip->addFromString('repeat.txt',str_repeat('x',200000));}
            else{$zip->addFromString($name,'bad');}
            $zip->close();
            try{try{$reader->read($path);self::fail('Unsafe archive accepted: '.$name);}catch(CatalogueException $e){self::assertContains($e->getStatusCode(),[413,422]);}}finally{unlink($path);}
        }
    }
    public function testExactDuplicateZipEntriesAndExcessiveJsonAreRejected():void
    {
        $local='';$central='';$name='same.txt';
        foreach(['first','second'] as $data) {
            $offset=strlen($local);$crc=crc32($data);$size=strlen($data);$length=strlen($name);
            $local.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,$length,0).$name.$data;
            $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,$length,0,0,0,0,0100644<<16,$offset).$name;
        }
        $path=tempnam(sys_get_temp_dir(),'duplicate-zip-');file_put_contents($path,$local.$central.pack('VvvvvVVv',0x06054b50,0,0,2,2,strlen($central),strlen($local),0));
        try{try{(new SafeArchive())->read($path);self::fail('Duplicate entry accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());}}finally{unlink($path);}
        $this->expectException(CatalogueException::class);StrictJson::decode('['.str_repeat('0,',20001).'0]');
    }
}
