<?php
namespace App\Tests\Integration;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageArchive;
use Styleguide\Installer\Project;
use Styleguide\Installer\Registries;
use Styleguide\Installer\RegistryClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
final class InstallerOriginTest extends KernelTestCase
{
    public function testRealRedirectCannotForwardRegistryCredentialsToAnotherOrigin():void
    {
        self::bootKernel();$directory=sys_get_temp_dir().'/registry-origin-'.bin2hex(random_bytes(6));mkdir($directory);$servers=[];$previous=getenv('STYLEGUIDE_TOKEN_TEST');putenv('STYLEGUIDE_TOKEN_TEST=sgt_'.str_repeat('a',64));
        try{
            $ports=[];for($i=0;$i<2;$i++){$socket=stream_socket_server('tcp://127.0.0.1:0');$ports[]=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);}
            file_put_contents($directory.'/redirect.php','<?php header("Location: http://127.0.0.1:'.$ports[1].'/collect");http_response_code(302);');
            file_put_contents($directory.'/foreign.php','<?php file_put_contents(__DIR__."/foreign-request","received");echo "{}";');
            foreach(['redirect','foreign']as$i=>$router){$process=new Process(['php','-S','127.0.0.1:'.$ports[$i],$directory.'/'.$router.'.php'],$directory);$process->start();$servers[]=$process;for($wait=0;$wait<100;$wait++){$socket=@stream_socket_client('tcp://127.0.0.1:'.$ports[$i],$errno,$error,.05);if($socket){fclose($socket);break;}usleep(10000);}}
            $project=new Project($directory,new CanonicalJson());$registries=new Registries($project);$registries->add('test','http://127.0.0.1:'.$ports[0],true,'assets/styles/app.input.css','assets/styles/app.css','assets/stimulus_bootstrap.js');$client=new RegistryClient($registries,HttpClient::create(),self::getContainer()->get(PackageArchive::class));
            try{$client->fetch('test','test/example','1.0.0');self::fail('Foreign redirect accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());self::assertStringContainsString('origin',$e->getMessage());}
            self::assertFileDoesNotExist($directory.'/foreign-request','The foreign origin received a request.');
        }finally{foreach($servers as $process)$process->stop(.2);if(false===$previous)putenv('STYLEGUIDE_TOKEN_TEST');else putenv('STYLEGUIDE_TOKEN_TEST='.$previous);foreach(glob($directory.'/*')as$file)unlink($file);rmdir($directory);}
    }
}
