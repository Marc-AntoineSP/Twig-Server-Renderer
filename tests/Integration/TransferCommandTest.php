<?php
namespace App\Tests\Integration;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Entity\Theme;
use App\Packaging\TransferExporter;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use App\Theme\ThemeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\ZipBuilder;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;
final class TransferCommandTest extends DatabaseTestCase
{
    private array $files=[];
    protected function tearDown():void{foreach($this->files as $path)if(is_file($path))unlink($path);parent::tearDown();}
    private function path(string $suffix):string{$p=sys_get_temp_dir().'/transfer-command-'.strtolower((string)new Ulid()).$suffix;$this->files[]=$p;return $p;}
    public function testPackageCommandsDryRunImportAndExportVerifiedBytesWithoutOverwrites():void
    {
        self::bootKernel();$this->account();$name='commands/item-'.strtolower((string)new Ulid());[$m,$s]=self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput($name,'component','CLI'));
        $m=self::getContainer()->get(PackageValidator::class)->validate($m,$s,true);$bytes=ZipBuilder::bytes(['manifest.json'=>self::getContainer()->get(CanonicalJson::class)->encode($m,true)."\n",...(array)$s]);$source=$this->path('.zip');file_put_contents($source,$bytes);
        $app=new Application(self::$kernel);$import=new CommandTester($app->find('styleguide:import'));self::assertSame(0,$import->execute(['file'=>$source,'--dry-run'=>true]));self::assertStringContainsString('no catalogue changes',$import->getDisplay());
        self::assertSame(0,$import->execute(['file'=>$source]));$export=new CommandTester($app->find('styleguide:export'));$output=$this->path('.zip');
        self::assertSame(0,$export->execute(['package'=>$name.'@'.$m->version,'--output'=>$output]));self::assertSame(hash('sha256',$bytes),hash_file('sha256',$output));
        self::assertSame(1,$export->execute(['package'=>$name.'@'.$m->version,'--output'=>$output]));self::assertSame(hash('sha256',$bytes),hash_file('sha256',$output));
    }
    public function testThemeCommandsValidateBeforeImportAndRequireExistingDraftRevision():void
    {
        self::bootKernel();$this->account();$doc=self::getContainer()->get(ThemeFactory::class)->create('commands/theme-'.strtolower((string)new Ulid()));$source=$this->path('.json');file_put_contents($source,json_encode($doc,JSON_THROW_ON_ERROR));$app=new Application(self::$kernel);$import=new CommandTester($app->find('styleguide:theme:import'));
        self::assertSame(0,$import->execute(['file'=>$source,'--dry-run'=>true]));$em=self::getContainer()->get(EntityManagerInterface::class);self::assertNull($em->getRepository(Theme::class)->findOneBy(['name'=>$doc->name]));
        self::assertSame(0,$import->execute(['file'=>$source]));self::assertSame(1,$import->execute(['file'=>$source]));
        $doc->colors->primary='#123456';file_put_contents($source,json_encode($doc,JSON_THROW_ON_ERROR));self::assertSame(0,$import->execute(['file'=>$source,'--revision'=>1]));$theme=$em->getRepository(Theme::class)->findOneBy(['name'=>$doc->name]);self::assertSame(2,$theme->getRevision());self::getContainer()->get(ThemeWriter::class)->publish($theme,2);
        $export=new CommandTester($app->find('styleguide:theme:export'));$output=$this->path('.zip');self::assertSame(0,$export->execute(['theme'=>$doc->name.'@1.0.0','--output'=>$output]));self::assertGreaterThan(1000,filesize($output));
    }
}
