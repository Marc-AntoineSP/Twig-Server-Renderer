<?php
namespace App\Packaging;
use App\Entity\User;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;
final readonly class ExportArtifacts
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/catalogue/exports')]private string $root){}
    public function create(User $owner,array $output):array
    {
        $directory=$this->root.'/'.$owner->getId();if(!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory))throw new \RuntimeException('Export storage unavailable.');
        $count=0;$bytes=0;
        foreach(glob($directory.'/*.json') as $meta){$m=StrictJson::decode(file_get_contents($meta),2048,8);$file=substr($meta,0,-5).'.bin';if(($m->expiresAt??0)<time()){if(is_file($file))unlink($file);unlink($meta);continue;}$count++;$bytes+=is_file($file)?filesize($file):0;}
        if($count>=20 || $bytes+strlen($output['bytes'])>104857600)throw new CatalogueException(429,'Export quota reached. Wait for 30-minute expiry.');
        $id=(string)new Ulid();$metadata=['name'=>$output['name'],'type'=>$output['type'],'sha256'=>hash('sha256',$output['bytes']),'expiresAt'=>time()+1800];
        $temp=tempnam($directory,'.stage-');
        try{if(file_put_contents($temp,$output['bytes'])!==strlen($output['bytes']) || !rename($temp,$directory.'/'.$id.'.bin'))throw new \RuntimeException('Cannot stage export.');
            $encoded=json_encode($metadata,JSON_THROW_ON_ERROR);
            if(file_put_contents($directory.'/'.$id.'.json',$encoded,LOCK_EX)!==strlen($encoded)){if(is_file($directory.'/'.$id.'.json'))unlink($directory.'/'.$id.'.json');unlink($directory.'/'.$id.'.bin');throw new \RuntimeException('Cannot store export metadata.');}
        }finally{if(is_file($temp))unlink($temp);}
        return ['id'=>$id,...$metadata];
    }
    public function read(User $owner,string $id):array
    {
        if(!Ulid::isValid($id))throw new CatalogueException(404,'Export not found.');$base=$this->root.'/'.$owner->getId().'/'.$id;
        if(!is_file($base.'.json') || !is_file($base.'.bin'))throw new CatalogueException(404,'Export not found.');
        $m=StrictJson::decode(file_get_contents($base.'.json'),2048,8);
        if($m->expiresAt<time())throw new CatalogueException(410,'Export expired. Build it again.');
        if(!hash_equals($m->sha256,hash_file('sha256',$base.'.bin')))throw new CatalogueException(422,'Export checksum mismatch.');
        return ['path'=>$base.'.bin','metadata'=>$m];
    }
}
