<?php
namespace App\Catalogue;
use App\Entity\CatalogueItem;
use App\Entity\Draft;
use App\Entity\User;
use Styleguide\Contracts\CatalogueException;
final readonly class Duplicator
{
    public function __construct(private CatalogueWriter $writer){}
    public function duplicate(CatalogueItem $item,string $name,User $owner):Draft
    {
        if(strlen($name)>100||!preg_match('~^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$~D',$name))throw new CatalogueException(422,'Choose a portable namespace/slug within 100 characters.');
        if($item->getKind()==='theme')throw new CatalogueException(422,'Duplicate typed themes by exporting/importing renamed token data.');
        $draft=$this->writer->draft($item);$manifest=$draft->getManifestData();$old=$item->getName();$before=['catalogue/'.$old.'/', 'catalogue/'.$old.'_controller.js','catalogue--'.str_replace('/','--',$old)];$after=['catalogue/'.$name.'/', 'catalogue/'.$name.'_controller.js','catalogue--'.str_replace('/','--',$name)];$sources=new \stdClass();
        foreach($draft->getSourceMap() as $path=>$source)$sources->{str_replace($before,$after,$path)}=$path==='meta/LICENSE'?$source:str_replace($before,$after,$source);
        if(isset($sources->{'meta/README.md'}))$sources->{'meta/README.md'}=str_replace($old,$name,$sources->{'meta/README.md'});
        $manifest->name=$name;$manifest->entry=str_replace($before,$after,$manifest->entry);$manifest->files=[];
        foreach($manifest->controllers as $controller){$controller->path=str_replace($before,$after,$controller->path);$controller->identifier=str_replace($before,$after,$controller->identifier);}
        return $this->writer->create(new CreateItemInput($name,$item->getKind(),mb_substr($item->getTitle(),0,113).' (copy)',$item->getDescription(),$item->getTags()),$owner,$manifest,$sources);
    }
}
