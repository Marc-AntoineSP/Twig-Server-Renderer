<?php
namespace Styleguide\Installer;
use Opis\JsonSchema\CompliantValidator;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\PortablePath;
use Styleguide\Contracts\StrictJson;
final readonly class Project
{
    public function __construct(public string $root,private CanonicalJson $json){}
    public function path(string $relative):string
    {
        PortablePath::check($relative);$current=$this->root;
        foreach(explode('/',$relative) as $segment){
            if(is_dir($current))foreach(new \DirectoryIterator($current) as $entry)if(0===strcasecmp($entry->getFilename(),$segment) && $entry->getFilename()!==$segment)throw new CatalogueException(409,'Case-insensitive destination collision: '.$relative);
            $current.='/'.$segment;if(is_link($current))throw new CatalogueException(409,'Symlink destination refused: '.$relative);
        }
        return $current;
    }
    public function defaults():array{return ['schemaVersion'=>1,'registries'=>[],'roots'=>[],'css'=>['input'=>'assets/styles/app.input.css','output'=>'assets/styles/app.css'],'bootstrap'=>'assets/stimulus_bootstrap.js','activeTheme'=>null];}
    public function config():array
    {
        $path=$this->path('styleguide.json');if(!is_file($path))return $this->defaults();$v=StrictJson::decode(file_get_contents($path),262144,16);
        if(!$v instanceof \stdClass || ($v->schemaVersion??null)!==1 || array_diff(array_keys((array)$v),array_keys($this->defaults())))throw new CatalogueException(422,'Invalid styleguide.json format.');
        $cfg=json_decode(json_encode($v,JSON_THROW_ON_ERROR),true);$cfg=array_replace($this->defaults(),$cfg);
        return $this->validated($cfg);
    }
    public function validated(array $cfg):array
    {
        if(!is_array($cfg['registries'])||!is_array($cfg['roots'])||!is_array($cfg['css'])||array_diff(array_keys($cfg['css']),['input','output']))throw new CatalogueException(422,'Invalid installer configuration.');
        foreach([$cfg['css']['input']??null,$cfg['css']['output']??null,$cfg['bootstrap']] as $file){if(!is_string($file))throw new CatalogueException(422,'Configure CSS input/output and Stimulus bootstrap paths.');$this->path($file);if(!str_starts_with($file,'assets/')||str_contains($file,'/catalogue/'))throw new CatalogueException(422,'Managed integration paths must be application assets outside package trees.');}
        if($cfg['css']['input']===$cfg['css']['output'])throw new CatalogueException(422,'CSS input and compiled output must be separate files.');
        foreach($cfg['registries'] as $alias=>$entry){if(!is_string($alias)||!preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$alias)||!is_array($entry)||array_diff(array_keys($entry),['url','allowHttp'])||!is_string($entry['url']??null)||!is_bool($entry['allowHttp']??null))throw new CatalogueException(422,'Invalid registry configuration; credentials belong outside the project.');Registries::validateUrl($entry['url'],$entry['allowHttp']);}
        return $cfg;
    }
    public function encodeConfig(array $config):string{$config['registries']=(object)$config['registries'];return $this->json->encode((object)$config,true)."\n";}
    public function lock():array
    {
        $path=$this->path('styleguide.lock.json');if(!is_file($path))return ['schemaVersion'=>1,'packages'=>[]];$value=StrictJson::decode(file_get_contents($path),2097152,32);
        $schema=dirname((new \ReflectionClass(PackageValidator::class))->getFileName()).'/../schemas/lock.schema.json';
        if(!(new CompliantValidator())->validate($value,file_get_contents($schema))->isValid())throw new CatalogueException(422,'Invalid styleguide.lock.json; refusing file operations.');
        $lock=json_decode(json_encode($value,JSON_THROW_ON_ERROR),true);$names=[];$paths=[];
        foreach($lock['packages'] as $package){if(isset($names[$package['name']]))throw new CatalogueException(422,'Duplicate locked package.');$names[$package['name']]=true;foreach($package['installedFiles'] as $file){$this->path($file['path']);if(isset($paths[strtolower($file['path'])]))throw new CatalogueException(422,'Cross-package ownership collision.');$paths[strtolower($file['path'])]=true;}}
        return $lock;
    }
    public function atomic(string $path,string $contents):void
    {
        if(!is_dir(dirname($path)) && !mkdir(dirname($path),0775,true) && !is_dir(dirname($path)))throw new CatalogueException(409,'Cannot create destination directory.');
        $temp=tempnam(dirname($path),'.styleguide-write-');
        try{$stream=fopen($temp,'wb');if(!$stream)throw new \RuntimeException('Cannot stage file.');try{if(fwrite($stream,$contents)!==strlen($contents)||!fflush($stream)||!fsync($stream))throw new \RuntimeException('Cannot flush staged file.');}finally{fclose($stream);}if(!rename($temp,$path))throw new \RuntimeException('Cannot install staged file.');}finally{if(is_file($temp))unlink($temp);}
    }
    public function encoded(mixed $value):string{return $this->json->encode($value,true)."\n";}
}
