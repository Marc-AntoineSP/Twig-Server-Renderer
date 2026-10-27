<?php
namespace App\Tests\Integration;
use App\Packaging\ThemePackager;
use App\Packaging\TransferExporter;
use App\Packaging\TransferImporter;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use App\Theme\ThemeWriter;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageSetReader;
use Symfony\Component\Uid\Ulid;
final class ThemePackageTest extends DatabaseTestCase
{
    public function testImmutableThemePackageContainsRoundTrippableSourcesAndCompiledOutput():void
    {
        self::bootKernel();$this->account();$doc=self::getContainer()->get(ThemeFactory::class)->create('package/theme-'.strtolower((string)new Ulid()));$writer=self::getContainer()->get(ThemeWriter::class);$theme=$writer->create($doc);$tr=$writer->publish($theme,1);
        $packager=self::getContainer()->get(ThemePackager::class);$release=$packager->publish($tr);$output=self::getContainer()->get(TransferExporter::class)->export($release,'source');
        $path=tempnam(sys_get_temp_dir(),'theme-package-test-');
        try{file_put_contents($path,$output['bytes']);$set=self::getContainer()->get(PackageSetReader::class)->read($path,'zip');$package=$set['packages'][$doc->name];self::assertSame('theme',$package['manifest']->kind);self::assertObjectHasProperty('assets/styles/catalogue/'.$doc->name.'/preview.css',$package['sources']);self::assertEquals($doc,json_decode($package['sources']->{'meta/theme.json'}));
            $prepared=self::getContainer()->get(TransferImporter::class)->prepare($path,'zip');self::assertSame('skip-identical',$prepared['plan']['packages'][0]['action']);
        }finally{unlink($path);}
        $this->expectException(CatalogueException::class);$packager->publish($tr);
    }
}
