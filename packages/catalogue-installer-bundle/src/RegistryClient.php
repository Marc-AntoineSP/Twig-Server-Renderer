<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageArchive;
use Styleguide\Contracts\StrictJson;
use Symfony\Contracts\HttpClient\HttpClientInterface;
final readonly class RegistryClient
{
    public function __construct(private Registries $registries,private HttpClientInterface $http,private PackageArchive $archives){}
    private function origin(string $url):string{$p=parse_url($url);if(!$p||!isset($p['scheme'],$p['host'])||isset($p['user'])||isset($p['pass']))throw new CatalogueException(422,'Invalid registry redirect origin.');return strtolower($p['scheme'].'://'.$p['host']).':'.($p['port']??('https'===$p['scheme']?443:80));}
    private function read(string $alias,string $url,int $limit):string
    {
        $definition=$this->registries->definition($alias);$origin=$this->origin($definition['url']);$token=$this->registries->credential($alias);
        for($redirect=0;$redirect<4;$redirect++){
            if($this->origin($url)!==$origin)throw new CatalogueException(422,'Registry redirect outside its configured origin refused.');
            try{$response=$this->http->request('GET',$url,['auth_bearer'=>$token,'max_redirects'=>0,'timeout'=>10,'max_duration'=>20,'buffer'=>false]);$status=$response->getStatusCode();
                if($status>=300&&$status<400){$location=$response->getHeaders(false)['location'][0]??'';if(str_starts_with($location,'/')){$p=parse_url($url);$location=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'').$location;}if(!preg_match('~^https?://~',$location)||isset(parse_url($location)['user'])||isset(parse_url($location)['pass']))throw new CatalogueException(422,'Unsupported registry redirect.');$url=$location;continue;}
                if(200!==$status)throw new CatalogueException(in_array($status,[401,403,404,429],true)?$status:502,'Registry request failed (HTTP '.$status.').');
                $length=$response->getHeaders(false)['content-length'][0]??null;if(null!==$length&&(int)$length>$limit){$response->cancel();throw new CatalogueException(413,'Registry response exceeds limits.');}
                $body='';foreach($this->http->stream($response) as $chunk){$body.=$chunk->getContent();if(strlen($body)>$limit){$response->cancel();throw new CatalogueException(413,'Registry response exceeds limits.');}}return $body;
            }catch(CatalogueException $e){throw $e;}catch(\Throwable){throw new CatalogueException(502,'Registry transport failed; credentials were not forwarded elsewhere.');}
        }
        throw new CatalogueException(422,'Too many registry redirects.');
    }
    public function fetch(string $alias,string $name,string $version):array
    {
        $base=$this->registries->definition($alias)['url'].'/'.$name;$info=StrictJson::decode($this->read($alias,$base,262144));
        if(!$info instanceof \stdClass||($info->schemaVersion??null)!==1||($info->name??null)!==$name||!is_array($info->versions??null))throw new CatalogueException(422,'Invalid registry metadata.');
        $hash=null;foreach($info->versions as $entry)if(($entry->version??null)===$version)$hash=$entry->archiveSha256??null;
        if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new CatalogueException(404,'Exact immutable version not listed by registry.');
        $manifest=StrictJson::decode($this->read($alias,$base.'/'.$version.'/manifest',262144));$bytes=$this->read($alias,$base.'/'.$version.'/archive',10485760);
        if(!hash_equals($hash,hash('sha256',$bytes)))throw new CatalogueException(422,'Registry archive checksum mismatch.');
        $file=tempnam(sys_get_temp_dir(),'installer-archive-');try{file_put_contents($file,$bytes);[$m,$sources]=$this->archives->read($file);}finally{unlink($file);}
        if($m->name!==$name||$m->version!==$version||$m!=$manifest)throw new CatalogueException(422,'Registry archive/manifest identity mismatch.');
        return ['manifest'=>$m,'sources'=>$sources,'archiveBytes'=>$bytes,'sha256'=>$hash,'registry'=>$alias];
    }
}
