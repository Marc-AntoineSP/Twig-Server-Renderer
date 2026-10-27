<?php
namespace App\Tests\Integration;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CollectionBuilder;
use Styleguide\Contracts\PackageSetReader;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\SafeArchive;
use Styleguide\Contracts\SafeYaml;
use Styleguide\Contracts\ZipBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class TransferCodecTest extends KernelTestCase
{
    private function read(string $bytes,string $format):array{$p=tempnam(sys_get_temp_dir(),'codec-test-');try{file_put_contents($p,$bytes);return self::getContainer()->get(PackageSetReader::class)->read($p,$format);}finally{unlink($p);}}
    public function testCanonicalSourceJsonAndYamlPreserveEmptyObjectsAndArchiveIntegrity():void
    {
        self::bootKernel();$json=self::getContainer()->get(CanonicalJson::class);
        foreach(['component','block'] as $kind){[$m,$s]=self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput('codec/'.$kind,$kind,'Codec'));$s->{'meta/README.md'}.="\nLiteral ! and * in content are not YAML aliases.\n";$m=self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);$doc=(object)['schemaVersion'=>1,'manifest'=>$m,'sources'=>$s];
            foreach(['json'=>$json->encode($doc,true),'yaml'=>SafeYaml::encode($doc)] as $format=>$bytes){$set=$this->read($bytes,$format);self::assertSame($json->encode($m),$json->encode($set['packages'][$m->name]['manifest']));self::assertSame($json->encode($s),$json->encode($set['packages'][$m->name]['sources']));self::assertSame(hash('sha256',ZipBuilder::bytes(['manifest.json'=>$json->encode($m,true)."\n",...(array)$s])),$set['packages'][$m->name]['sha256']);}
        }
        $set=self::getContainer()->get(PackageSetReader::class)->read(self::getContainer()->getParameter('kernel.project_dir').'/agent-kit/examples/packages/alert/manifest.json',adjacent:true);self::assertArrayHasKey('awui/alert',$set['packages']);
    }
    public function testCollectionRejectsHashMismatchAndUnresolvedDependencies():void
    {
        self::bootKernel();[$m,$s]=self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput('codec/missing','component','Missing'));
        $m->dependencies=[(object)['name'=>'missing/leaf','version'=>'1.0.0']];$m=self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);$json=self::getContainer()->get(CanonicalJson::class);$archive=ZipBuilder::bytes(['manifest.json'=>$json->encode($m,true)."\n",...(array)$s]);$package=['manifest'=>$m,'sources'=>$s,'archiveBytes'=>$archive];
        $collection=self::getContainer()->get(CollectionBuilder::class)->bytes([$m->name=>$package],[(object)['name'=>$m->name,'version'=>$m->version]]);
        try{$this->read($collection,'zip');self::fail('Unresolved collection accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());}
        $index=(object)['schemaVersion'=>1,'type'=>'collection','roots'=>[(object)['name'=>$m->name,'version'=>$m->version]],'packages'=>[(object)['name'=>$m->name,'version'=>$m->version,'path'=>'packages/'.$m->name.'/'.$m->version.'.zip','sha256'=>str_repeat('0',64)]]];
        $this->expectException(CatalogueException::class);$this->read(ZipBuilder::bytes(['index.json'=>$json->encode($index),'packages/'.$m->name.'/'.$m->version.'.zip'=>$archive]),'zip');
    }
}
