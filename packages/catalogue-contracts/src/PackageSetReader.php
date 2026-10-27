<?php
namespace Styleguide\Contracts;
final readonly class PackageSetReader
{
    public function __construct(private SafeArchive $archives,private PackageArchive $packages,private PackageValidator $validator,private CanonicalJson $json){}
    public function read(string $path,?string $format=null,bool $adjacent=false):array
    {
        if(!is_file($path) || filesize($path)>10485760) throw new CatalogueException(413,'Import must be a local file within 10 MiB.');
        $format??=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        if('zip'===$format) return $this->zip($path);
        $bytes=file_get_contents($path);
        $value=match($format){'json'=>StrictJson::decode($bytes,3145728),'yaml','yml'=>SafeYaml::decode($bytes),default=>throw new CatalogueException(422,'Use ZIP, JSON or YAML.')};
        if(!$value instanceof \stdClass || ($value->schemaVersion??null)!==1) throw new CatalogueException(422,'Unknown import format; schemaVersion 1 is required.');
        if(isset($value->manifest,$value->sources)) {
            if(array_diff(array_keys((array)$value),['schemaVersion','manifest','sources']) || !$value->manifest instanceof \stdClass || !$value->sources instanceof \stdClass) throw new CatalogueException(422,'Use an envelope with schemaVersion, manifest and sources.');
            $manifest=$value->manifest;$sources=$value->sources;
        }elseif($adjacent && isset($value->name,$value->files)) {
            if(!is_string($value->name) || !preg_match('~^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$~D',$value->name) || !is_array($value->files) || count($value->files)>100)throw new CatalogueException(422,'Invalid bounded manifest identity/inventory.');
            $manifest=$value;$sources=new \stdClass();$root=realpath(dirname($path));
            $total=0;
            foreach($manifest->files as $file) {
                if(!is_object($file) || !isset($file->path) || !is_string($file->path)) throw new CatalogueException(422,'Invalid file inventory.');
                $this->validator->sourcePath($file->path,$manifest->name);$current=$root;
                foreach(explode('/',$file->path) as $segment){$current.='/'.$segment;if(is_link($current))throw new CatalogueException(422,'Package files may not use symlinks.');}
                $real=realpath($current);
                if(!$real || !str_starts_with($real,$root.'/') || !is_file($real) || filesize($real)>1048576) throw new CatalogueException(422,'Missing or excessive manifest payload file.');
                $sources->{$file->path}=file_get_contents($real);
                if(($total+=strlen($sources->{$file->path}))>2097152)throw new CatalogueException(413,'Manifest sources exceed 2 MiB.');
            }
        }else throw new CatalogueException(422,'Upload a complete ZIP or manifest/sources envelope. A CLI manifest may read adjacent declared files.');
        $manifest=$this->validator->validate($manifest,$sources);
        $archive=ZipBuilder::bytes(['manifest.json'=>$this->json->encode($manifest,true)."\n",...(array)$sources]);
        return ['roots'=>[(object)['name'=>$manifest->name,'version'=>$manifest->version]],'packages'=>[$manifest->name=>$this->package($manifest,$sources,$archive)]];
    }
    private function package(\stdClass $manifest,\stdClass $sources,string $archive):array{return ['manifest'=>$manifest,'sources'=>$sources,'archiveBytes'=>$archive,'sha256'=>hash('sha256',$archive)];}
    private function zip(string $path):array
    {
        $files=$this->archives->read($path);
        if(isset($files['manifest.json'])) {[$m,$s]=$this->packages->read($path);return ['roots'=>[(object)['name'=>$m->name,'version'=>$m->version]],'packages'=>[$m->name=>$this->package($m,$s,file_get_contents($path))]];}
        if(!isset($files['index.json'])) throw new CatalogueException(422,'ZIP needs manifest.json or a resolved collection index.json.');
        $index=StrictJson::decode($files['index.json']);
        if(!$index instanceof \stdClass || ($index->schemaVersion??null)!==1 || ($index->type??null)!=='collection' || !is_array($index->roots??null) || !is_array($index->packages??null) || !$index->roots || count($index->packages)>100 || array_diff(array_keys((array)$index),['schemaVersion','type','roots','packages'])) throw new CatalogueException(422,'Invalid collection index.');
        $used=['index.json'=>true];if(isset($files['README.md']))$used['README.md']=true;$packages=[];
        foreach($index->packages as $entry) {
            if(!$entry instanceof \stdClass || !isset($entry->name,$entry->version,$entry->path,$entry->sha256) || array_diff(array_keys((array)$entry),['name','version','path','sha256']) || (!is_string($entry->path) || !is_string($entry->name) || !is_string($entry->version))) throw new CatalogueException(422,'Invalid collection package entry.');
            PortablePath::check($entry->path);
            if(isset($used[$entry->path]) || !isset($files[$entry->path]) || !is_string($entry->sha256) || !hash_equals(hash('sha256',$files[$entry->path]),$entry->sha256)) throw new CatalogueException(422,'Missing, duplicate or checksum-mismatched collection archive.');
            $temp=tempnam(sys_get_temp_dir(),'collection-package-');
            try{file_put_contents($temp,$files[$entry->path]);[$m,$s]=$this->packages->read($temp);}finally{unlink($temp);}
            if($m->name!==$entry->name || $m->version!==$entry->version || $entry->path!=='packages/'.$m->name.'/'.$m->version.'.zip' || isset($packages[$m->name])) throw new CatalogueException(409,'Collection identity/version collision.');
            $used[$entry->path]=true;$packages[$m->name]=$this->package($m,$s,$files[$entry->path]);
        }
        if(count($used)!==count($files)) throw new CatalogueException(422,'Unlisted files in collection.');
        $roots=[];
        foreach($index->roots as $root) {
            if(!$root instanceof \stdClass || !isset($root->name,$root->version) || array_diff(array_keys((array)$root),['name','version']) || !is_string($root->name) || !is_string($root->version) || !isset($packages[$root->name]) || $packages[$root->name]['manifest']->version!==$root->version || isset($roots[$root->name])) throw new CatalogueException(422,'Invalid or duplicate collection root.');
            $roots[$root->name]=$root;
        }
        $reachable=[];
        $visit=function(string $name) use(&$visit,&$reachable,$packages):void {
            if(isset($reachable[$name]))return;$reachable[$name]=true;
            foreach($packages[$name]['manifest']->dependencies as $dependency){
                if(!isset($packages[$dependency->name]) || $packages[$dependency->name]['manifest']->version!==$dependency->version)throw new CatalogueException(422,'Resolved collection is missing an exact dependency archive.');
                $visit($dependency->name);
            }
        };
        foreach($roots as $root)$visit($root->name);
        if(count($reachable)!==count($packages))throw new CatalogueException(422,'Collection contains packages outside its declared roots.');
        return ['roots'=>array_values($roots),'packages'=>$packages];
    }
}
