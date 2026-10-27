<?php
namespace App\Composition;

use App\Catalogue\{CatalogueWriter,DraftInput};
use App\Entity\{CatalogueItem,CompositionRevision,Draft,Release,User};
use App\Rendering\{DependencyResolver,ReleaseSourceReader};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CatalogueException,StrictJson};
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** The same authoritative validation/save/history path for manual and MCP editors. */
final readonly class CompositionWorkspace
{
    public function __construct(private CatalogueWriter $writer,private CompositionTree $trees,private CompositionCompiler $compiler,private EntityManagerInterface $em,private AuthorizationCheckerInterface $auth,private DependencyResolver $resolver,private ReleaseSourceReader $releases){}

    public function require(CatalogueItem $item,string $action='COMPOSE_READ'):Draft
    {
        if(!$this->auth->isGranted($action,$item))throw new CatalogueException(403,'Composer permission or catalogue scope is insufficient.',[], 'FORBIDDEN');
        $draft=$this->writer->draft($item);
        if(!isset($draft->getSourceMap()->{'meta/composition.json'}))throw new CatalogueException(409,'This item belongs to the source editor. Only composition-owned blocks/layouts are editable here.',[], 'UNSUPPORTED_EDITOR');
        return $draft;
    }
    public function document(Draft $draft):\stdClass
    {
        $doc=StrictJson::decode($draft->getSourceMap()->{'meta/composition.json'},65536,64);
        return $this->trees->normalize($doc,$draft->getItem()->getId());
    }
    public function expected(Draft $draft,int $expected):void
    {
        if($expected<1||$draft->getRevision()!==$expected)throw new CatalogueException(412,'Composition changed. Read the latest revision and merge your unsaved work.', ['currentRevision'=>[$draft->getRevision()]],'REVISION_CONFLICT');
    }
    public function prepare(CatalogueItem $item,int $expected,\stdClass $document):array
    {
        $draft=$this->require($item);$this->expected($draft,$expected);
        $doc=$this->trees->normalize($document,$item->getId());
        [$manifest,$sources]=$this->compiler->materialize($draft->getManifestData(),$draft->getSourceMap(),$doc);
        $this->authorizeReferences($manifest,$sources);
        return ['document'=>$doc,'revision'=>$draft->getRevision(),'manifestData'=>$manifest,'sourceMap'=>$sources];
    }
    public function commands(CatalogueItem $item,int $expected,\stdClass $document,array $commands):array
    {
        $draft=$this->require($item,'COMPOSE_EDIT');$this->expected($draft,$expected);
        // Reference insertion with omitted props always uses that exact release's defaults.
        foreach($commands as $command)if($command instanceof \stdClass && ($command->type??'')==='add_reference'&&!isset($command->props)) {
            if(($command->name??null)===$item->getName())throw new CatalogueException(422,'A composition cannot reference itself.',['name'=>[$item->getName()]],'REFERENCE_CYCLE');
            $reference=$this->release($command->name??'', $command->version??'');$command->props=$reference->getManifestData()->defaults;
        }
        $result=$this->trees->apply($document,$item->getId(),$commands);
        $this->prepare($item,$expected,$result['document']);
        return $result+['revision'=>$draft->getRevision()];
    }
    public function save(CatalogueItem $item,int $expected,\stdClass $document,User $actor,?string $title=null,?string $description=null,string $summary='Saved composition'):Draft
    {
        $this->require($item,'COMPOSE_EDIT');
        return $this->em->wrapInTransaction(function()use($item,$expected,$document,$actor,$title,$description,$summary):Draft{
            $draft=$this->writer->draft($item);$this->em->refresh($draft,LockMode::PESSIMISTIC_WRITE);$this->expected($draft,$expected);
            $prepared=$this->prepare($item,$expected,$document);
            $this->capture($draft,$actor,'Before '.$summary);
            $saved=$this->writer->save($item,$expected,new DraftInput($prepared['manifestData'],$prepared['sourceMap'],$title??$item->getTitle(),$description??$item->getDescription(),$item->getTags()),$actor);
            $this->capture($saved,$actor,$summary);
            return $saved;
        });
    }
    public function capture(Draft $draft,User $actor,string $summary):void
    {
        $item=$draft->getItem();
        if($this->em->getRepository(CompositionRevision::class)->findOneBy(['item'=>$item,'revision'=>$draft->getRevision()]))return;
        $this->em->persist(new CompositionRevision($item,$draft->getRevision(),$this->document($draft),$item->getTitle(),$item->getDescription(),$actor,mb_substr($summary,0,200)));
        $this->em->flush();
    }
    public function release(string $name,string $version):Release
    {
        $item=$this->em->getRepository(CatalogueItem::class)->findOneBy(['namespace'=>explode('/',$name,2)[0],'slug'=>explode('/',$name,2)[1]??'']);
        if(!$item||!$this->auth->isGranted('COMPOSE_READ',$item))throw new CatalogueException(404,'Accessible immutable reference not found.',[],'NOT_FOUND');
        return $this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version])??throw new CatalogueException(404,'Immutable reference version not found.',[],'NOT_FOUND');
    }
    public function referenceDetails(Release $release):array
    {
        if(!$this->auth->isGranted('COMPOSE_READ',$release->getItem()))throw new CatalogueException(404,'Reference not found.',[],'NOT_FOUND');
        $m=$release->getManifestData();$sources=$this->releases->read($release);
        $this->authorizeReferences($m,$sources);
        return ['itemId'=>$release->getItem()->getId(),'releaseId'=>$release->getId(),'name'=>$m->name,'kind'=>$m->kind,'version'=>$m->version,'props'=>$m->defaults,'propsSchema'=>StrictJson::decode($sources->{$m->propsSchema},65536,24),'summary'=>$m->description??''];
    }
    public function authorizeReferences(\stdClass $manifest,\stdClass $sources):void
    {
        // Resolve the exact closure first (including version/cycle/integrity checks),
        // then authorize every dependency. Nested blocks cannot bypass token scopes.
        $this->resolver->resolve($manifest,$sources);
        $seen=[];foreach($manifest->dependencies as $d)$this->authorizeDependency($d->name,$d->version,$seen);
    }
    private function authorizeDependency(string $name,string $version,array &$seen):void
    {
        $key=$name.'@'.$version;if(isset($seen[$key]))return;$seen[$key]=true;
        $release=$this->release($name,$version);
        foreach($release->getManifestData()->dependencies as $d)$this->authorizeDependency($d->name,$d->version,$seen);
    }
}
