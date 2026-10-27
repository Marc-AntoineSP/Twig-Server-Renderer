<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
final readonly class Registries
{
    public function __construct(private Project $project){}
    public static function validateUrl(string $url,bool $local):string
    {
        $p=parse_url($url);
        if(!$p || !isset($p['scheme'],$p['host']) || (isset($p['user'])||isset($p['pass'])) || isset($p['query']) || isset($p['fragment']) || preg_match('/[\x00-\x20\x7f]/',$url))throw new CatalogueException(422,'Use a registry URL without credentials, query or fragment.');
        if(!in_array($p['scheme'],['https','http'],true))throw new CatalogueException(422,'Registry requires HTTPS.');
        if('http'===$p['scheme']){
            $host=$p['host'];$private='localhost'===$host || '::1'===$host || '[::1]'===$host || preg_match('/^[a-z][a-z0-9-]*$|\.(?:test|localhost|internal)$/D',$host) || preg_match('/^(?:127\.|10\.|192\.168\.|172\.(?:1[6-9]|2[0-9]|3[01])\.)/',$host);
            if(!$local||!$private)throw new CatalogueException(422,'Plain HTTP is accepted only with --allow-http for an explicitly local development registry.');
        }
        return rtrim($url,'/');
    }
    public function add(string $name,string $url,bool $local,string $input,string $output,string $bootstrap):void
    {
        if(!preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$name)||'file'===$name)throw new CatalogueException(422,'Choose a registry alias (file is reserved).');
        $cfg=$this->project->config();$definition=['url'=>$this->validateUrl($url,$local),'allowHttp'=>$local];
        if(isset($cfg['registries'][$name])&&$cfg['registries'][$name]!==$definition)throw new CatalogueException(409,'Registry alias already points elsewhere. Use another alias.');
        $cfg['registries'][$name]=$definition;$cfg['css']=['input'=>$input,'output'=>$output];$cfg['bootstrap']=$bootstrap;
        foreach([$input,$output,$bootstrap] as $path)$this->project->path($path);
        $this->project->validated($cfg);$this->project->atomic($this->project->path('styleguide.json'),$this->project->encodeConfig($cfg));
    }
    public function definition(string $alias):array{$cfg=$this->project->config();$entry=$cfg['registries'][$alias]??throw new CatalogueException(422,'Configure the registry with styleguide:registry:add first.');if(!is_array($entry)||array_diff(array_keys($entry),['url','allowHttp'])||!is_string($entry['url']??null)||!is_bool($entry['allowHttp']??null))throw new CatalogueException(422,'Invalid registry definition.');$entry['url']=$this->validateUrl($entry['url'],$entry['allowHttp']);return $entry;}
    public function credential(string $alias):string
    {
        $env='STYLEGUIDE_TOKEN_'.strtoupper(str_replace('-','_',$alias));$token=getenv($env);
        if(false===$token||''===$token){$base=getenv('XDG_CONFIG_HOME')?:((getenv('HOME')?:'').'/.config');$file=$base.'/styleguide/auth.json';if(is_file($file)){if((fileperms($file)&0077)!==0)throw new CatalogueException(403,'Private registry auth file must use mode 0600.');$auth=StrictJson::decode(file_get_contents($file),65536,8);$token=$auth->{$alias}??null;}}
        if(!is_string($token)||!preg_match('/^sgt_[a-f0-9]{64}$/D',$token))throw new CatalogueException(401,'Set '.$env.' or a mode-0600 per-user styleguide/auth.json credential.');
        return $token;
    }
}
