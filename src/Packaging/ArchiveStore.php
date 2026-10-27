<?php
namespace App\Packaging;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ZipBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class ArchiveStore
{
    public function __construct(private CanonicalJson $json,#[Autowire('%kernel.project_dir%/var/catalogue')]private string $root){}
    public function put(\stdClass $manifest,\stdClass $sources):array{return $this->putBytes(ZipBuilder::bytes(['manifest.json'=>$this->json->encode($manifest,true)."\n",...(array)$sources]));}
    public function putBytes(string $bytes):array
    {
        if(!is_dir($this->root)&&!mkdir($this->root,0770,true)&&!is_dir($this->root))throw new \RuntimeException('Archive storage unavailable.');
        $lock=fopen($this->root.'/.storage.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Archive storage unavailable.');
        try{
            $hash=hash('sha256',$bytes);$key='releases/'.$hash.'.zip';$existing=$this->root.'/'.$key;
            if(is_file($existing)&&hash_equals($hash,hash_file('sha256',$existing))){touch($existing);return [$key,$hash];}
            $files=glob($this->root.'/releases/*.zip')?:[];$size=0;foreach($files as $file)$size+=filesize($file);
            if(count($files)>=20000||$size+strlen($bytes)>2147483648)throw new CatalogueException(429,'Immutable archive storage quota reached; run cleanup and review capacity.');
            return $this->store($bytes);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function store(string $bytes):array
    {
        if(strlen($bytes)>10485760) throw new CatalogueException(413,'Archive exceeds 10 MiB.');
        $directory=$this->root.'/releases';if(!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory)) throw new \RuntimeException('Archive storage unavailable.');
        $temp=tempnam($directory,'.stage-');
        try{
            if(file_put_contents($temp,$bytes)!==strlen($bytes)) throw new \RuntimeException('Cannot stage archive.');
            $hash=hash('sha256',$bytes);$key='releases/'.$hash.'.zip';
            if(!@link($temp,$this->root.'/'.$key) && (!is_file($this->root.'/'.$key) || !hash_equals($hash,hash_file('sha256',$this->root.'/'.$key)))) throw new \RuntimeException('Cannot install immutable archive.');
            return [$key,$hash];
        }finally{if(is_file($temp))unlink($temp);}
    }
}
