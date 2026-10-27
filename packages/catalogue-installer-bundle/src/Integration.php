<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\Process\Process;
final readonly class Integration
{
    public function __construct(private Project $project){}
    public static function strip(string $text,string $kind):string
    {
        $pattern='css'===$kind?'~/\* styleguide:v1:start \*/.*?/\* styleguide:v1:end \*/\n?~s':'~// styleguide:v1:start\n.*?// styleguide:v1:end\n?~s';
        if(substr_count($text,'styleguide:v1:start')>1||substr_count($text,'styleguide:v1:start')!==substr_count($text,'styleguide:v1:end'))throw new CatalogueException(409,'Managed integration markers are inconsistent.');
        return preg_replace($pattern,'',$text);
    }
    private function relative(string $from,string $to):string{$a=explode('/',dirname($from));$b=explode('/',$to);while($a&&$b&&$a[0]===$b[0]){array_shift($a);array_shift($b);}$relative=str_repeat('../',count($a)).implode('/',$b);return str_starts_with($relative,'.')?$relative:'./'.$relative;}
    private function quote(string $value):string{return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';}
    public function build(array $packages,array $oldPackages,array $cfg,string $directory):array
    {
        $input=$cfg['css']['input'];$bootstrap=$cfg['bootstrap'];$cssBase=self::strip(file_get_contents($this->project->path($input)),'css');$jsBase=self::strip(file_get_contents($this->project->path($bootstrap)),'js');
        $controllers=[];$scan='';$styles=[];$active=null;
        foreach($packages as $name=>$p){$m=$p['manifest'];foreach($p['sources'] as $path=>$source){$scan.=$source."\n";if('theme'!==$m->kind&&str_ends_with($path,'.css'))$styles[]=$path;}
            foreach($m->controllers as $controller)$controllers[]=$controller;
            if('theme'===$m->kind&&$cfg['activeTheme']===$m->name.'@'.$m->version){$active=StrictJson::decode($p['sources']->{'meta/theme.json'},65536,24);$styles[]='assets/styles/catalogue/'.$name.'/theme.fragment.css';$styles[]='assets/styles/catalogue/'.$name.'/typography.css';}}
        $loader="// Generated Styleguide controller registrations.\n";$registrations=[];
        foreach($controllers as $i=>$controller){$loader.='import Controller'.$i.' from '.json_encode($this->relative('assets/styleguide/controllers.js',$controller->path),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).";\n";$registrations[]='  app.register('.json_encode($controller->identifier).', Controller'.$i.');';}
        $loader.="export function registerCatalogueControllers(app) {\n".implode("\n",$registrations)."\n}\n";
        $cssBlock="/* styleguide:v1:start */\n@source ".$this->quote($this->relative($input,'.styleguide/sources.html')).";\n@source not ".$this->quote($this->relative($input,'.styleguide/packages')).";\n@source not ".$this->quote($this->relative($input,'.styleguide/staging')).";\n";
        foreach($styles as $path)$cssBlock.='@import '.$this->quote($this->relative($input,$path)).";\n";$cssBlock.="/* styleguide:v1:end */\n";
        $jsBlock="// styleguide:v1:start\nimport { registerCatalogueControllers } from ".json_encode($this->relative($bootstrap,'assets/styleguide/controllers.js'),JSON_UNESCAPED_SLASHES).";\nregisterCatalogueControllers(app);\n// styleguide:v1:end\n";
        $managed=[$input=>$cssBase.($packages?$cssBlock:''),$bootstrap=>$jsBase.($packages?$jsBlock:''),'.styleguide/sources.html'=>$packages?$scan:null,'assets/styleguide/controllers.js'=>$packages?$loader:null];
        // Stage every proposed source; no consumer destination is changed during compilation.
        $stage=$directory.'/new';foreach($packages as $p)foreach($p['sources'] as $path=>$bytes)if(!str_starts_with($path,'meta/'))$this->project->atomic($stage.'/'.$path,$bytes);
        $this->project->atomic($stage.'/.styleguide/sources.html',$scan);
        $root=$this->project->root;$cssDir=$root.'/'.dirname($input);
        $compile=preg_replace_callback('/@(import|config|source|reference|plugin)\s+([\'"])([^\'"]+)\2/',function($m)use($cssDir){$target=$m[3];if(str_starts_with($target,'.'))$target=$cssDir.'/'.$target;return '@'.$m[1].' '.$this->quote($target);},$cssBase);
        $compile.="\n@source ".$this->quote($stage.'/.styleguide/sources.html').";\n";
        foreach(array_unique([...array_keys($oldPackages),...array_keys($packages)]) as $name)foreach(['templates/catalogue/'.$name,'assets/controllers/catalogue/'.$name,'assets/controllers/catalogue/'.$name.'_controller.js','assets/catalogue/'.$name,'assets/styles/catalogue/'.$name] as $path)$compile.='@source not '.$this->quote($root.'/'.$path).";\n";
        foreach($styles as $path)$compile.='@import '.$this->quote($stage.'/'.$path).";\n";
        $this->project->atomic($directory.'/compiler/input.css',$compile);$env=[];foreach(array_unique([...array_keys(getenv()),...array_keys($_ENV),...array_keys($_SERVER)])as$key)if(is_string($key))$env[$key]=false;$env=[...$env,'PATH'=>'/usr/local/bin:/usr/bin:/bin','LANG'=>'C.UTF-8','RAYON_NUM_THREADS'=>'1','NODE_ENV'=>'production'];
        $sources=new \stdClass();foreach($packages as $p)foreach($p['sources'] as $path=>$source)if('theme'!==$p['manifest']->kind||!str_ends_with($path,'.css'))$sources->{$path}=$source;
        $validator=new Process(['node','--max-old-space-size=128',dirname(__DIR__).'/tooling/validate.mjs',$root],$root,$env);$validator->setInput(json_encode($sources,JSON_THROW_ON_ERROR));$validator->setTimeout(10);$validator->run();
        if(!$validator->isSuccessful()){$error=json_decode($validator->getOutput(),true);throw new CatalogueException(422,'Source validation failed: '.($error['error']??'Fixed parser unavailable; check doctor.'));}
        $process=new Process(['node','--max-old-space-size=256',$root.'/node_modules/@tailwindcss/cli/dist/index.mjs','-i',$directory.'/compiler/input.css','-o',$directory.'/compiler/output.css','--minify'],$root,$env);$process->setTimeout(30);$process->run();if(!$process->isSuccessful())throw new CatalogueException(422,'Fixed Tailwind compilation failed: '.substr($process->getErrorOutput(),0,1200));
        $managed[$cfg['css']['output']]=file_get_contents($directory.'/compiler/output.css');return ['files'=>$managed,'activeTheme'=>$active?->cssName];
    }
}
