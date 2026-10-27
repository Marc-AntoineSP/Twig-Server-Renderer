<?php
namespace App\Packaging;
use App\Entity\Release;
use App\Entity\ThemeRelease;
use App\Rendering\PreviewRenderer;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CollectionBuilder;
use Styleguide\Contracts\PackageGraph;
use Styleguide\Contracts\PropsValidator;
use Styleguide\Contracts\SafeYaml;
use Styleguide\Contracts\StrictJson;
use Styleguide\Contracts\ZipBuilder;
final readonly class TransferExporter
{
    public function __construct(private ReleaseSourceReader $reader,private CatalogueRepository $catalogue,private EntityManagerInterface $em,private PackageGraph $graphs,private PreviewRenderer $renderer,private PropsValidator $props,private CanonicalJson $json,private CollectionBuilder $collections,private ThemePackager $themes,private \App\Font\ThemeFontAssets $fontAssets){}
    public function find(string $name,string $version):Release
    {
        $item=$this->catalogue->named($name);return $item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version])??throw new CatalogueException(404,'Release not found.'):throw new CatalogueException(404,'Package not found.');
    }
    public function export(Release $release,string $format,?ThemeRelease $theme=null,?\stdClass $props=null):array
    {
        $m=$release->getManifestData();$s=$this->reader->read($release);$prefix=str_replace('/','-',$m->name).'-'.$m->version;
        if('source'===$format || 'theme'===$format)return ['bytes'=>file_get_contents($this->reader->path($release)),'name'=>$prefix.'.zip','type'=>'application/zip'];
        if(in_array($format,['json','yaml'],true)){
            $envelope=(object)['schemaVersion'=>1,'manifest'=>$m,'sources'=>$s];
            $bytes='json'===$format?$this->json->encode($envelope,true)."\n":SafeYaml::encode($envelope,10485760);
            if(strlen($bytes)>10485760)throw new CatalogueException(413,'Font data exceeds the 10 MiB envelope profile; use source ZIP.');
            return ['bytes'=>$bytes,'name'=>$prefix.'.'.$format,'type'=>'json'===$format?'application/json':'application/yaml'];
        }
        $closure=$this->graphs->resolve($m,$s,function($name,$version){$r=$this->find($name,$version);return [$r->getManifestData(),$this->reader->read($r)];});
        if('collection'===$format){
            $packages=[];$roots=[(object)['name'=>$m->name,'version'=>$m->version]];
            foreach($closure['packages'] as $name=>$package){$r=$this->find($name,$package['manifest']->version);$packages[$name]=[...$package,'archiveBytes'=>file_get_contents($this->reader->path($r))];}
            if($theme){$tp=$this->themes->package($theme);$name=$tp['manifest']->name;if(isset($packages[$name]) && $packages[$name]['manifest']->version!==$tp['manifest']->version)throw new CatalogueException(409,'Selected theme version conflicts with dependencies.');$packages[$name]??=$tp;if(!in_array($name,array_map(static fn($r)=>$r->name,$roots),true))$roots[]=(object)['name'=>$name,'version'=>$tp['manifest']->version];}
            return ['bytes'=>$this->collections->bytes($packages,$roots),'name'=>$prefix.'-collection.zip','type'=>'application/zip'];
        }
        if('static'!==$format || 'theme'===$m->kind)throw new CatalogueException(422,'Choose source, collection, static, JSON or YAML for a renderable release.');
        $props??=$m->defaults;$this->props->validateValue($props,StrictJson::decode($s->{$m->propsSchema},65536,24));
        unset($closure['packages']);$tokens=$theme?->getDocument()??$closure['theme'];
        $preset=$tokens?['bodyFont'=>'theme','headingFont'=>'theme','rootSize'=>$tokens->typography->rootFontSizePx,'textClass'=>$tokens->typography->textClass]:[];
        $payload=json_decode(json_encode([...$closure,'props'=>$props,'channel'=>bin2hex(random_bytes(16)),'theme'=>$tokens,'preset'=>$preset],JSON_THROW_ON_ERROR),true,64,JSON_THROW_ON_ERROR);
        $result=$this->renderer->render($payload);if(!$result['ok'])throw new CatalogueException(422,'Static export failed: '.$result['error']['message']);
        $files=['index.html'=>$result['html'],'preview.css'=>$result['css'],'preview.js'=>$result['js'],'README.md'=>"Offline HTML snapshot of ".$m->name.'@'.$m->version.". index.html embeds the compiled CSS/JS and restrictive CSP.\npreview.css/preview.js are labelled standalone compiled outputs for inspection.\nNo studio requests, cookies or backend are required. This is a rendered snapshot, not a Twig runtime.\n"];
        foreach($closure['versions'] as $name=>$version){$r=$this->find($name,$version);$sources=$this->reader->read($r);$files['licenses/'.$name.'/LICENSE']=$sources->{'meta/LICENSE'}??('Declared licence: '.$r->getManifestData()->license);}
        if($theme)$files['licenses/'.$theme->getTheme()->getName().'/LICENSE']='UNLICENSED; no additional rights granted by the studio.';
        if($tokens)$files=[...$files,...$this->fontAssets->files($tokens)];
        $inventory=[];foreach($files as $path=>$bytes){$raw=\Styleguide\Contracts\SourceBytes::raw($path,$bytes);$inventory[$path]=['sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw)];}
        $files['checksums.json']=$this->json->encode($inventory,true)."\n";
        return ['bytes'=>ZipBuilder::bytes($files),'name'=>$prefix.'-static.zip','type'=>'application/zip'];
    }
}
