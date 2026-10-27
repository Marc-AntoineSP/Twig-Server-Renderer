<?php
namespace App\Tests;
use PHPUnit\Framework\TestCase;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Installer\Journal;
use Styleguide\Installer\PortableTwig;
use Styleguide\Installer\Project;
use Styleguide\Installer\Registries;
final class InstallerSafetyTest extends TestCase
{
    private string $directory;
    protected function setUp():void{$this->directory=sys_get_temp_dir().'/installer-'.bin2hex(random_bytes(6));mkdir($this->directory);}
    protected function tearDown():void{$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($this->directory);}
    public function testCaseCollisionAndSymlinkCannotEscapeTheConsumer():void
    {
        $project=new Project($this->directory,new CanonicalJson());mkdir($this->directory.'/Assets');
        try{$project->path('assets/new.css');self::fail('Case collision allowed.');}catch(CatalogueException $e){self::assertSame(409,$e->getStatusCode());}
        symlink('/tmp',$this->directory.'/linked');$this->expectException(CatalogueException::class);$project->path('linked/escape.css');
    }
    public function testRecoveryRestoresOnlyInterruptedOwnedHashes():void
    {
        $project=new Project($this->directory,new CanonicalJson());$stage=$this->directory.'/.styleguide/staging/test';$project->atomic($stage.'/before/0','old');$project->atomic($this->directory.'/assets/test.css','new');
        $journal=['schemaVersion'=>1,'state'=>'applying','entries'=>[['path'=>'assets/test.css','index'=>0,'beforeSha'=>hash('sha256','old'),'afterSha'=>hash('sha256','new')]],'directories'=>[]];$project->atomic($stage.'/journal.json',$project->encoded($journal));
        self::assertSame(['test'],(new Journal($project))->recover());self::assertSame('old',file_get_contents($this->directory.'/assets/test.css'));
        $project->atomic($stage.'/before/0','old');$project->atomic($stage.'/journal.json',$project->encoded($journal));$project->atomic($this->directory.'/assets/test.css','user modification');
        try{(new Journal($project))->recover();self::fail('External edit overwritten.');}catch(CatalogueException $e){self::assertSame(409,$e->getStatusCode());self::assertSame('user modification',file_get_contents($this->directory.'/assets/test.css'));self::assertFileExists($stage.'/journal.json');}
    }
    public function testPortableTwigBlocksGlobalsMethodsFunctionsAndDynamicIncludes():void
    {
        $validator=new PortableTwig();foreach(['{{ app.secret }}','{{ request.headers }}','{{ user.name }}','{{ _context }}','{{ value.method() }}','{{ constant("PHP_VERSION") }}','{% include selected %}','{% include "base.html.twig" %}','{% include "catalogue/test/one.html.twig" %}'] as $source){
            try{$validator->validate(['test/one'=>['sources'=>(object)['templates/catalogue/test/one.html.twig'=>$source]]]);self::fail('Unsafe template accepted: '.$source);}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());}
        }
        $validator->validate(['test/one'=>['sources'=>(object)['templates/catalogue/test/one.html.twig'=>'{% include "catalogue/test/two.html.twig" with {message: "hello"} only %}','templates/catalogue/test/two.html.twig'=>'{{ message|escape }}']]]);self::assertTrue(true);
    }
    public function testRegistryRejectsCredentialsAndPlainPublicHttp():void
    {
        foreach(['http://example.com/api','https://token@example.com/api','https://example.com/api?token=secret','https://example.com/api#fragment'] as $url){try{Registries::validateUrl($url,true);self::fail('Unsafe URL accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());}}
        Registries::validateUrl('http://studio/api/v1/registry',true);self::assertTrue(true);
    }
}
