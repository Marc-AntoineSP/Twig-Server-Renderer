<?php
namespace App\Composition;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CompositionTemplate;
use Styleguide\Contracts\StrictJson;
use App\Entity\Release;
use App\Rendering\DependencyResolver;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
final readonly class CompositionCompiler
{
    public const EMPTY_TEMPLATE="<section class=\"grid gap-4\">\n\n</section>\n";
    public function __construct(private CompositionTemplate $templates,private CatalogueRepository $catalogue,private EntityManagerInterface $em,private ReleaseSourceReader $reader,private CanonicalJson $json,private DependencyResolver $resolver) {}
    public function materialize(\stdClass $manifest,\stdClass $sources,\stdClass $document,bool $verify=false):array
    {
        if(!isset($manifest->kind,$manifest->entry,$manifest->name,$manifest->dependencies) || !is_array($manifest->dependencies) || !in_array($manifest->kind,['block','layout'],true)) throw new CatalogueException(422,'Composition manifest is invalid.');
        [$twig,$dependencies]=$this->templates->compile($document,$manifest->name,function($name,$version) {
            $item=$this->catalogue->named($name);$release=$item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version]):null;
            if(!$release) throw new CatalogueException(422,'Unknown immutable reference '.$name.'@'.$version.'.');
            return [$release->getManifestData(),$this->reader->read($release)];
        });
        if($verify && (($sources->{$manifest->entry}??null)!==$twig || $this->json->encode($manifest->dependencies)!==$this->json->encode($dependencies))) throw new CatalogueException(409,'Composition owns generated Twig and dependencies. Edit the tree or duplicate and convert to source.');
        $manifest=clone $manifest;$sources=clone $sources;$manifest->dependencies=$dependencies;
        $sources->{$manifest->entry}=$twig;$sources->{'meta/composition.json'}=$this->json->encode($document,true)."\n";
        $this->resolver->resolve($manifest,$sources);
        return [$manifest,$sources];
    }
    public function verify(\stdClass $manifest,\stdClass $sources):void
    {
        if(!isset($sources->{'meta/composition.json'})) return;
        $document=StrictJson::decode($sources->{'meta/composition.json'},65536);
        if(!$document instanceof \stdClass) throw new CatalogueException(422,'Composition must be an object.');
        $this->materialize($manifest,$sources,$document,true);
    }
}
