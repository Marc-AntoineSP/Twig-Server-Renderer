<?php
namespace Styleguide\Contracts;
final readonly class PackageGraph
{
    public function __construct(private PackageValidator $validator,private CompositionTemplate $compositions,private CanonicalJson $json) {}
    public function resolve(\stdClass $manifest,\stdClass $sources,callable $lookup):array
    {
        $versions=[];$visiting=[];$packages=[];$allSources=new \stdClass();$templates=new \stdClass();$controllers=[];$identifiers=[];$safelist=[];$bytes=0;$theme=null;$themeNames=[];
        $visit=function(\stdClass $m,\stdClass $files,int $depth) use(&$visit,&$versions,&$visiting,&$packages,$allSources,$templates,&$controllers,&$identifiers,&$safelist,&$bytes,&$theme,&$themeNames,$lookup):void {
            $m=$this->validator->validate($m,$files,0===$depth);
            if($depth>20 || (!isset($versions[$m->name]) && count($versions)>=100)) throw new CatalogueException(422,'Dependency graph exceeds depth/package limits.');
            if(isset($visiting[$m->name])) throw new CatalogueException(422,'Dependency cycle detected at '.$m->name.'.');
            if(isset($versions[$m->name]) && $versions[$m->name]!==$m->version) throw new CatalogueException(409,'Conflicting exact versions for '.$m->name.'.');
            if(isset($packages[$m->name])) return;
            $versions[$m->name]=$m->version;$visiting[$m->name]=true;
            foreach($m->dependencies as $dependency) { [$dm,$df]=$lookup($dependency->name,$dependency->version); if($dm->name!==$dependency->name || $dm->version!==$dependency->version) throw new CatalogueException(422,'Dependency identity mismatch.'); $visit($dm,$df,$depth+1); }
            if(isset($files->{'meta/composition.json'})) {
                [$twig,$deps]=$this->compositions->compile(StrictJson::decode($files->{'meta/composition.json'},65536),$m->name,function($name,$version) use(&$packages,$m) {
                    if(!in_array($name,array_map(static fn($d)=>$d->name,$m->dependencies),true) || !isset($packages[$name]) || $packages[$name]['manifest']->version!==$version) throw new CatalogueException(422,'Composition reference is outside the exact dependency closure.');
                    return [$packages[$name]['manifest'],$packages[$name]['sources']];
                });
                if($twig!==$files->{$m->entry} || $this->json->encode($deps)!==$this->json->encode($m->dependencies)) throw new CatalogueException(409,'Generated composition source differs from its tree.');
            }
            if('theme'===$m->kind) {
                $tokens=StrictJson::decode($files->{'meta/theme.json'},65536,24);
                if(isset($themeNames[$tokens->cssName]) && $themeNames[$tokens->cssName]!==$tokens->name) throw new CatalogueException(409,'Theme CSS names collide.');
                $themeNames[$tokens->cssName]=$tokens->name;
                $theme??=$tokens;
            }
            foreach($files as $path=>$source) {
                if(str_ends_with($path,'.woff2')) continue;
                $key=str_starts_with($path,'meta/')?'metadata/'.$m->name.'/'.substr($path,5):$path;
                if(property_exists($allSources,$key) || ($bytes+=strlen($source))>2097152 || count((array)$allSources)>=200) throw new CatalogueException(422,'Dependency closure has colliding paths or exceeds source limits.');
                // Generated theme directives are typed data, never compiler input configuration.
                if('theme'!==$m->kind || !str_ends_with($path,'.css')) $allSources->$key=$source;
                if(str_starts_with($path,'templates/')) $templates->{substr($path,10)}=$source;
            }
            foreach($m->controllers as $controller) { if(isset($identifiers[$controller->identifier])) throw new CatalogueException(409,'Controller identifier collision.');$identifiers[$controller->identifier]=true;$controllers[]=$controller; }
            $safelist=array_values(array_unique([...$safelist,...$m->safelist]));
            unset($visiting[$m->name]);$packages[$m->name]=['manifest'=>$m,'sources'=>$files];
        };
        $visit($manifest,$sources,0);
        return ['entry'=>substr($manifest->entry,10),'templates'=>$templates,'sources'=>$allSources,'controllers'=>$controllers,'safelist'=>$safelist,'versions'=>$versions,'theme'=>$theme,'packages'=>$packages];
    }
}
