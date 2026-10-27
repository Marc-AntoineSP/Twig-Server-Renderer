<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageGraph;
use Styleguide\Contracts\PackageReference;
use Styleguide\Contracts\PackageSetReader;
use Styleguide\Contracts\StrictJson;
final readonly class Installer
{
    public function __construct(private Project $project,private RegistryClient $registry,private PackageSetReader $reader,private PackageGraph $graphs,private Compatibility $compatibility,private PortableTwig $twig,private Integration $integration,private Journal $journal){}
    public function run(string $operation,?string $argument,?string $alias,bool $dry=false):array
    {
        $lockPath=$this->project->path('.styleguide/install.lock');if(!is_dir(dirname($lockPath)))mkdir(dirname($lockPath),0775,true);$handle=fopen($lockPath,'c+');if(!$handle||!flock($handle,LOCK_EX|LOCK_NB))throw new CatalogueException(409,'Another installer operation holds the project lock.');
        try{
            if($dry&&$this->journal->pending())throw new CatalogueException(409,'Interrupted installation found. Run styleguide:doctor --recover.');
            if(!$dry)$this->journal->recover();
            $versions=$this->compatibility->doctor();$cfg=$this->project->config();$oldLock=$this->project->lock();$snapshot=[];foreach(['styleguide.json','styleguide.lock.json'] as $path){$absolute=$this->project->path($path);$snapshot[$path]=is_file($absolute)?hash_file('sha256',$absolute):null;}$old=[];$oldRecords=[];
            foreach($oldLock['packages'] as $record){$name=$record['name'];$archive=$this->project->path('.styleguide/packages/'.$name.'/'.$record['version'].'/source.zip');if(!is_file($archive)||!hash_equals($record['archiveSha256'],hash_file('sha256',$archive)))throw new CatalogueException(409,'Installed archive integrity failure: '.$name);
                $set=$this->reader->read($archive,'zip');$old[$name]=[...$set['packages'][$name],'registry'=>$record['registry']];$oldRecords[$name]=$record;}
            $roots=$cfg['roots'];$incoming=[];$wantedTheme=null;
            if('install'===$operation){$set=$this->reader->read($argument,adjacent:true);foreach($set['packages'] as $name=>$p)$incoming[$name]=[...$p,'registry'=>'file'];foreach($set['roots'] as $root)$roots=$this->root($roots,$root->name,$root->version,'file');}
            elseif(in_array($operation,['require','theme','update'],true)){
                [$name,$version]=PackageReference::parse($argument);
                if('update'===$operation){$root=current(array_filter($roots,static fn($r)=>$r['name']===$name));if(!$root)throw new CatalogueException(409,'Update a root package; pinned dependencies must be updated through their parent.');$alias=$root['registry'];if('file'===$alias)throw new CatalogueException(422,'Install a newer local collection to update file-origin packages.');}
                if(!$alias)throw new CatalogueException(422,'Supply --registry=ALIAS.');$incoming[$name]=$this->registry->fetch($alias,$name,$version);$roots=$this->root($roots,$name,$version,$alias);if('update'===$operation && str_starts_with($cfg['activeTheme']??'',$name.'@'))$wantedTheme=$name.'@'.$version;if('theme'===$operation){if('theme'!==$incoming[$name]['manifest']->kind)throw new CatalogueException(422,'theme:install requires a theme package.');$wantedTheme=$name.'@'.$version;}
            }elseif('remove'===$operation){$name=$argument;if(!isset($old[$name]))throw new CatalogueException(404,'Package is not installed.');foreach($old as $parent=>$p)if($parent!==$name)foreach($p['manifest']->dependencies as $d)if($d->name===$name)throw new CatalogueException(409,$name.' is pinned by '.$parent.'; remove/update the dependent first.');$roots=array_values(array_filter($roots,static fn($r)=>$r['name']!==$name));if($cfg['activeTheme']===$name.'@'.$old[$name]['manifest']->version)$cfg['activeTheme']=null;}
            else throw new CatalogueException(422,'Unknown installer operation.');
            $packages=[];$bytes=0;$files=0;$ids=[];$themeNames=[];
            foreach($roots as $root){
                if(!is_array($root)||array_diff(array_keys($root),['name','version','registry'])||!isset($root['name'],$root['version'],$root['registry']))throw new CatalogueException(422,'Invalid configured roots.');
                PackageReference::parse($root['name'].'@'.$root['version']);$source=$root['registry'];
                $lookup=function(string $name,string $version)use(&$incoming,$old,$source):array{
                    if(isset($incoming[$name])&&$incoming[$name]['manifest']->version!==$version)throw new CatalogueException(409,'Incompatible required versions for '.$name);
                    if(!isset($incoming[$name])){if(isset($old[$name])&&$old[$name]['manifest']->version===$version)$incoming[$name]=$old[$name];elseif('file'!==$source)$incoming[$name]=$this->registry->fetch($source,$name,$version);else throw new CatalogueException(422,'Local collection lacks exact dependency '.$name.'@'.$version);}
                    return [$incoming[$name]['manifest'],$incoming[$name]['sources']];
                };
                [$m,$s]=$lookup($root['name'],$root['version']);$closure=$this->graphs->resolve($m,$s,$lookup);
                foreach($closure['packages'] as $name=>$p){$p=[...$p,'archiveBytes'=>$incoming[$name]['archiveBytes'],'sha256'=>$incoming[$name]['sha256'],'registry'=>$incoming[$name]['registry']];
                    if(isset($packages[$name])){if($packages[$name]['manifest']->version!==$p['manifest']->version)throw new CatalogueException(409,'Roots require conflicting package versions.');continue;}
                    if(isset($old[$name])&&$old[$name]['manifest']->version===$p['manifest']->version&&!hash_equals($old[$name]['sha256'],$p['sha256']))throw new CatalogueException(409,'Immutable installed version has different registry bytes.');
                    $this->compatibility->package($p['manifest'],$versions);foreach($p['sources']as$path=>$source)$bytes+=strlen(\Styleguide\Contracts\SourceBytes::raw($path,$source));$files+=count((array)$p['sources']);if($bytes>10485760||$files>200||count($packages)>=100)throw new CatalogueException(413,'Installed closure exceeds the 10-MiB/200-file/100-package profile.');
                    foreach($p['manifest']->controllers as $controller){if(isset($ids[$controller->identifier]))throw new CatalogueException(409,'Controller identity collision across roots.');$ids[$controller->identifier]=true;}
                    if('theme'===$p['manifest']->kind){$t=StrictJson::decode($p['sources']->{'meta/theme.json'},65536,24);if(isset($themeNames[$t->cssName]))throw new CatalogueException(409,'Theme CSS-name collision across roots.');$themeNames[$t->cssName]=$name;}
                    $packages[$name]=$p;
                }
            }
            $this->twig->validate($packages);ksort($packages);usort($roots,static fn($a,$b)=>strcmp($a['name'],$b['name']));$cfg['roots']=$roots;
            if($wantedTheme)$cfg['activeTheme']=$wantedTheme;
            if(null===$cfg['activeTheme']){foreach($packages as $p)if('theme'===$p['manifest']->kind){$t=StrictJson::decode($p['sources']->{'meta/theme.json'},65536,24);if($t->default){$cfg['activeTheme']=$p['manifest']->name.'@'.$p['manifest']->version;break;}}}
            if($cfg['activeTheme']){[$tn,$tv]=PackageReference::parse($cfg['activeTheme']);if(!isset($packages[$tn])||$packages[$tn]['manifest']->version!==$tv)throw new CatalogueException(409,'Active theme is outside the installed closure.');}
            return $this->stage($packages,$old,$oldRecords,$cfg,$oldLock,$dry,$snapshot);
        }finally{flock($handle,LOCK_UN);fclose($handle);}
    }
    private function root(array $roots,string $name,string $version,string $registry):array{$roots=array_values(array_filter($roots,static fn($r)=>$r['name']!==$name));$roots[]=['name'=>$name,'version'=>$version,'registry'=>$registry];return $roots;}
    private function stage(array $packages,array $old,array $oldRecords,array $cfg,array $oldLock,bool $dry,array $snapshot):array
    {
        $directory=$this->project->path('.styleguide/staging/'.bin2hex(random_bytes(10)));mkdir($directory,0775,true);$desired=[];$records=[];$owners=[];$oldFiles=[];$managedBefore=[];$conflicts=[];
        foreach($oldRecords as $name=>$record){foreach($record['installedFiles'] as $file){$oldFiles[$file['path']]=$file['sha256'];$snapshot[$file['path']]=$file['sha256'];$path=$this->project->path($file['path']);if(!is_file($path)||!hash_equals($file['sha256'],hash_file('sha256',$path)))$conflicts[]='Modified/missing owned file: '.$file['path'];}
            foreach($record['managedEdits'] as $edit){if(isset($managedBefore[$edit['path']])&&$managedBefore[$edit['path']]['afterSha256']!==$edit['afterSha256'])throw new CatalogueException(422,'Inconsistent managed hashes in lock.');$managedBefore[$edit['path']]=$edit;$snapshot[$edit['path']]=$edit['afterSha256'];$path=$this->project->path($edit['path']);if(!is_file($path)||!hash_equals($edit['afterSha256'],hash_file('sha256',$path)))$conflicts[]='Modified managed file: '.$edit['path'];}}
        foreach($packages as $name=>$p){$m=$p['manifest'];$base='.styleguide/packages/'.$name.'/'.$m->version.'/';$payload=[];foreach($p['sources'] as $path=>$source)$payload[str_starts_with($path,'meta/')?$base.$path:$path]=\Styleguide\Contracts\SourceBytes::raw($path,$source);$payload[$base.'manifest.json']=$this->project->encoded($m);$payload[$base.'source.zip']=$p['archiveBytes'];ksort($payload);$installed=[];
            foreach($payload as $path=>$source){$absolute=$this->project->path($path);if(!array_key_exists($path,$snapshot))$snapshot[$path]=is_file($absolute)?hash_file('sha256',$absolute):null;if(isset($owners[strtolower($path)]))throw new CatalogueException(409,'Cross-package destination collision.');$owners[strtolower($path)]=$name;if(file_exists($absolute)&&!isset($oldFiles[$path]))$conflicts[]='Unowned destination collision: '.$path;$desired[$path]=$source;$installed[]=['path'=>$path,'sha256'=>hash('sha256',$source)];}
            $records[$name]=['name'=>$name,'version'=>$m->version,'registry'=>$p['registry'],'archiveSha256'=>$p['sha256'],'dependencies'=>json_decode(json_encode($m->dependencies,JSON_THROW_ON_ERROR),true),'installedFiles'=>$installed,'managedEdits'=>[]];}
        foreach([$cfg['css']['input'],$cfg['bootstrap']] as $path)if(!isset($managedBefore[$path])&&str_contains(file_get_contents($this->project->path($path)),'styleguide:v1:start'))$conflicts[]='Unowned integration marker: '.$path;
        foreach([$cfg['css']['input'],$cfg['css']['output'],$cfg['bootstrap'],'.styleguide/sources.html','assets/styleguide/controllers.js'] as $path){if(!array_key_exists($path,$snapshot)){$absolute=$this->project->path($path);$snapshot[$path]=is_file($absolute)?hash_file('sha256',$absolute):null;}}
        $integrated=$this->integration->build($packages,$old,$cfg,$directory);$edits=[];
        foreach($integrated['files'] as $path=>$source){$absolute=$this->project->path($path);$generated=in_array($path,['.styleguide/sources.html','assets/styleguide/controllers.js'],true);
            if($generated&&file_exists($absolute)&&!isset($managedBefore[$path]))$conflicts[]='Unowned integration destination: '.$path;
            $desired[$path]=$source;
            if(null!==$source){$before=$managedBefore[$path]['beforeSha256']??hash('sha256',is_file($absolute)?file_get_contents($absolute):'');$edits[]=['path'=>$path,'marker'=>$generated?'styleguide-generated-v1':'styleguide-integration-v1','beforeSha256'=>$before,'afterSha256'=>hash('sha256',$source)];}
        }
        foreach($records as &$record)$record['managedEdits']=$edits;unset($record);
        foreach($oldFiles as $path=>$hash)if(!array_key_exists($path,$desired))$desired[$path]=null;
        // A removed integration helper is deleted only after its known hash was checked above.
        foreach($managedBefore as $path=>$edit)if(!array_key_exists($path,$desired)&&$edit['marker']==='styleguide-generated-v1')$desired[$path]=null;
        $desired['styleguide.json']=$this->project->encodeConfig($cfg);$newLock=['schemaVersion'=>1,'packages'=>array_values($records)];$desired['styleguide.lock.json']=$this->project->encoded($newLock);
        foreach($snapshot as $path=>$hash){$absolute=$this->project->path($path);if((is_file($absolute)?hash_file('sha256',$absolute):null)!==$hash)$conflicts[]='Changed during preparation: '.$path;}
        $changes=[];foreach($desired as $path=>$source){$absolute=$this->project->path($path);$current=is_file($absolute)?file_get_contents($absolute):null;if($current!==$source)$changes[$path]=$source;}
        $review=[];foreach($packages as $p)foreach($p['manifest']->controllers as $controller)$review[]=['package'=>$p['manifest']->name,'registry'=>$p['registry'],'path'=>$controller->path,'source'=>$p['sources']->{$controller->path}];
        $plan=['schemaVersion'=>1,'packages'=>array_map(static fn($p)=>['name'=>$p['manifest']->name,'version'=>$p['manifest']->version,'registry'=>$p['registry'],'archiveSha256'=>$p['sha256'],'license'=>$p['manifest']->license],array_values($packages)),'activeTheme'=>$cfg['activeTheme'],'files'=>array_map(static fn($path,$bytes)=>['path'=>$path,'action'=>null===$bytes?'remove':'write','sha256'=>null===$bytes?null:hash('sha256',$bytes)],array_keys($changes),array_values($changes)),'browserCodeReview'=>$review,'conflicts'=>array_values(array_unique($conflicts)),'candidateDirectory'=>substr($directory,strlen($this->project->root)+1)];
        foreach($changes as $path=>$bytes)if(null!==$bytes)$this->project->atomic($directory.'/candidates/'.$path,$bytes);
        $this->project->atomic($directory.'/plan.json',$this->project->encoded($plan));
        if($conflicts)throw new CatalogueException(409,'Install aborted without destination writes. Conflicts: '.implode('; ',array_slice(array_unique($conflicts),0,10)).'. Review '.$plan['candidateDirectory'].'/plan.json');
        if($dry)return ['dryRun'=>true,'changed'=>count($changes),'plan'=>$plan];
        if(!$changes){$this->journal->clean($directory);return ['dryRun'=>false,'changed'=>0,'plan'=>$plan];}
        // The lockfile is always the final destination mutation.
        $last=$changes['styleguide.lock.json']??null;unset($changes['styleguide.lock.json']);if(null!==$last)$changes['styleguide.lock.json']=$last;
        $this->journal->apply($directory,$changes,$snapshot);return ['dryRun'=>false,'changed'=>count($changes),'plan'=>$plan];
    }
    public function doctor(bool $recover=false):array
    {
        $result=$this->compatibility->doctor();if($recover){$path=$this->project->path('.styleguide/install.lock');if(!is_dir(dirname($path)))mkdir(dirname($path),0775,true);$lock=fopen($path,'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new CatalogueException(409,'Installer lock is held.');try{$result['recovered']=$this->journal->recover();}finally{flock($lock,LOCK_UN);fclose($lock);}}
        $issues=[];foreach($this->project->lock()['packages'] as $p){foreach($p['installedFiles'] as $file){$path=$this->project->path($file['path']);if(!is_file($path)||!hash_equals($file['sha256'],hash_file('sha256',$path)))$issues[]='Modified/missing '.$file['path'];}foreach($p['managedEdits'] as $file){$path=$this->project->path($file['path']);if(!is_file($path)||!hash_equals($file['afterSha256'],hash_file('sha256',$path)))$issues[]='Modified integration '.$file['path'];}}
        if($this->journal->pending())$issues[]='Interrupted transaction: run styleguide:doctor --recover.';$result['issues']=array_values(array_unique($issues));$result['healthy']=!$issues;return $result;
    }
}
