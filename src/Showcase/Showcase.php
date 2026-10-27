<?php
namespace App\Showcase;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Composition\CompositionCompiler;
use App\Entity\Release;
use App\Packaging\PublishInput;
use App\Packaging\ReleasePublisher;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class Showcase
{
    public function __construct(private SourceFactory $factory,private CatalogueWriter $writer,private CatalogueRepository $catalogue,private ReleasePublisher $publisher,private ReleaseSourceReader $reader,private PackageValidator $validator,private CanonicalJson $json,private CompositionCompiler $compositions,private EntityManagerInterface $em,#[Autowire('%kernel.project_dir%/fixtures/showcase')]private string $directory){}
    public function seed(bool $publish=false):array
    {
        $results=[];$verified=[];$definitions=StrictJson::decode(file_get_contents($this->directory.'/index.json'));
        foreach($definitions as $definition){$name='studio-showcase/'.$definition->slug;$input=new CreateItemInput($name,'component',$definition->title,'Portable interaction and accessibility example.',['showcase']);[$manifest,$sources]=$this->factory->initial($input);
            $manifest->defaults=$definition->defaults;$sources->{'meta/props.schema.json'}=$this->json->encode((object)['type'=>'object','properties'=>$definition->properties,'required'=>array_keys((array)$definition->properties),'additionalProperties'=>false]);
            $sources->{$manifest->entry}=str_replace('__CONTROLLER__','catalogue--studio-showcase--'.$definition->slug,file_get_contents($this->directory.'/'.$definition->slug.'/component.html.twig'));
            $script=$this->directory.'/'.$definition->slug.'/controller.js';if(is_file($script)){$path='assets/controllers/catalogue/'.$name.'_controller.js';$sources->{$path}=file_get_contents($script);$manifest->controllers=[(object)['identifier'=>'catalogue--studio-showcase--'.$definition->slug,'path'=>$path]];}
            if(isset($definition->license)){$manifest->license=$definition->license;$sources->{'meta/LICENSE'}=file_get_contents($this->directory.'/'.$definition->slug.'/LICENSE');}
            $examples=[(object)['name'=>'Default','props'=>$manifest->defaults]];
            if($definition->slug==='button')foreach($definition->properties->variant->enum as $variant)$examples[]=(object)['name'=>$variant,'props'=>(object)[...(array)$manifest->defaults,'variant'=>$variant]];
            $sources->{'meta/examples.json'}=$this->json->encode($examples);$manifest=$this->validator->validate($manifest,$sources,true);
            $verified[$definition->slug]=$this->store($input,$manifest,$sources,$publish,$results);
        }
        if($publish&&$verified['card']){
            $input=new CreateItemInput('studio-showcase/pricing','block','Pricing comparison','Illustrative plans composed from immutable cards.',['showcase']);[$m,$s]=$this->factory->initial($input);$children=[];
            foreach(['Starter','Team','Scale'] as $title)$children[]=(object)['type'=>'reference','name'=>'studio-showcase/card','version'=>'1.0.0','props'=>(object)['title'=>$title,'body'=>'Illustrative '.$title.' plan.','details'=>'Compare the source and adapt this plan to your product.']];
            $tree=(object)['schemaVersion'=>1,'root'=>(object)['type'=>'container','tag'=>'section','classes'=>['grid','gap-4','md:grid-cols-3'],'children'=>$children]];[$m,$s]=$this->compositions->materialize($m,$s,$tree);$m=$this->validator->validate($m,$s,true);$verified['pricing']=$this->store($input,$m,$s,true,$results);
        }
        if($publish&&count(array_filter($verified))===11){
            $input=new CreateItemInput('studio-showcase/dashboard','layout','Portable dashboard','Keyboard-friendly composed showcase with real browser behavior.',['showcase']);[$m,$s]=$this->factory->initial($input);$children=[];
            foreach($definitions as $d)$children[]=(object)['type'=>'reference','name'=>'studio-showcase/'.$d->slug,'version'=>'1.0.0','props'=>$d->defaults];$children[]=(object)['type'=>'reference','name'=>'studio-showcase/pricing','version'=>'1.0.0','props'=>new \stdClass()];
            $tree=(object)['schemaVersion'=>1,'root'=>(object)['type'=>'container','tag'=>'main','classes'=>['space-y-6'],'children'=>$children]];[$m,$s]=$this->compositions->materialize($m,$s,$tree);$m=$this->validator->validate($m,$s,true);$this->store($input,$m,$s,true,$results);
        }
        return $results;
    }
    private function store(CreateItemInput $input,\stdClass $manifest,\stdClass $sources,bool $publish,array &$results):bool
    {
        $item=$this->catalogue->named($input->name);$created=!$item;if(!$item)$item=$this->writer->create($input,null,$manifest,$sources)->getItem();$draft=$this->writer->draft($item);
        if(!$publish){$results[$input->name]=$created?'draft created':'existing draft preserved';return false;}
        $release=$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>'1.0.0']);
        if($release){$expected=clone $manifest;$expected->version='1.0.0';$same=$this->json->encode($expected)===$this->json->encode($release->getManifestData())&&$this->json->encode($sources)===$this->json->encode($this->reader->read($release));$results[$input->name]=$same?'verified immutable release preserved':'existing release preserved; bundled composition skipped';return $same;}
        if($this->json->encode($manifest)!==$this->json->encode($draft->getManifestData())||$this->json->encode($sources)!==$this->json->encode($draft->getSourceMap())){$results[$input->name]='modified draft preserved; no automatic release';return false;}
        $this->publisher->publish($item,new PublishInput('1.0.0',$draft->getRevision()));$results[$input->name]='validated 1.0.0 release created';return true;
    }
}
