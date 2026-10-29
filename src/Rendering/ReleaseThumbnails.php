<?php
namespace App\Rendering;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class ReleaseThumbnails
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/catalogue/thumbnails')]private string $directory){}
    public function store(string $hash,string $html):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||strlen($html)>262144)return;
        if(!is_dir($this->directory)&&!mkdir($this->directory,0770,true)&&!is_dir($this->directory))return;$lock=fopen($this->directory.'/.quota.lock','c+');if(!$lock||!flock($lock,LOCK_EX))return;
        try{$path=$this->directory.'/'.$hash.'.html';if(is_file($path))return;$size=0;foreach(glob($this->directory.'/*.html')?:[] as $file)$size+=filesize($file);if($size+strlen($html)>52428800)return;$temporary=tempnam($this->directory,'.stage-');try{if(file_put_contents($temporary,$html)===strlen($html))rename($temporary,$path);}finally{if(is_file($temporary))unlink($temporary);}}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function available(?string $hash): bool
    {
        if (!$hash || !preg_match('/^[a-f0-9]{64}$/D', $hash)) return false;
        $path = $this->directory.'/'.$hash.'.html';
        return is_file($path) && !is_link($path) && filesize($path) <= 262144;
    }
    public function read(?string $hash):?string
    {
        if(!$hash||!preg_match('/^[a-f0-9]{64}$/D',$hash))return null;$path=$this->directory.'/'.$hash.'.html';return is_file($path)&&!is_link($path)&&filesize($path)<=262144?file_get_contents($path):null;
    }
}
