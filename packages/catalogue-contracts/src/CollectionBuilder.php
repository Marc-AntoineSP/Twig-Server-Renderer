<?php
namespace Styleguide\Contracts;
final readonly class CollectionBuilder
{
    public function __construct(private CanonicalJson $json){}
    public function bytes(array $packages,array $roots):string
    {
        ksort($packages);$index=(object)['schemaVersion'=>1,'type'=>'collection','roots'=>$roots,'packages'=>[]];$files=[];
        foreach($packages as $package) {
            $m=$package['manifest'];$path='packages/'.$m->name.'/'.$m->version.'.zip';$bytes=$package['archiveBytes'];
            $files[$path]=$bytes;$index->packages[]=(object)['name'=>$m->name,'version'=>$m->version,'path'=>$path,'sha256'=>hash('sha256',$bytes)];
        }
        $files['index.json']=$this->json->encode($index,true)."\n";
        $files['README.md']="Resolved Styleguide collection. All package archives, exact dependencies, inventories and licences are included.\nInstall locally with styleguide:install collection.zip in a compatible Symfony consumer.\nRun --dry-run first to review origin, browser JavaScript and destination changes.\nNo studio credentials are included. Browser JavaScript runs in the receiving document after installation.\nTheme app.css/fragment/typography are editable Tailwind SOURCE; preview.css is COMPILED sample output.\nNever import a second Tailwind root into an existing app.css; the installer manages integration once.\n";
        return ZipBuilder::bytes($files);
    }
}
