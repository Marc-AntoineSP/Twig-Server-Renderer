<?php
namespace App\Mcp;

use App\Catalogue\{CatalogueWriter,CreateItemInput};
use App\Composition\{CompositionCompiler,CompositionTree,CompositionWorkspace};
use App\Entity\{CatalogueItem,CompositionPreview,Release,ThemeRelease,User};
use App\Mcp\Security\ComposeIdentity;
use App\Rendering\{PreviewInput,PreviewJobs,PreviewLifecycle,PreviewStorage,RendererSettings};
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CatalogueException,StrictJson};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Per-call application adapter. No selected composition, actor or credential is cached. */
final readonly class ComposeApplication
{
    public function __construct(private \App\Composition\CompositionHistory $historyService,private CompositionWorkspace $workspace,private CompositionTree $trees,private CompositionCompiler $compiler,private CatalogueWriter $writer,private EntityManagerInterface $em,private ComposeOperations $operations,private TokenStorageInterface $security,private AuthorizationCheckerInterface $auth,private UrlGeneratorInterface $urls,private PreviewJobs $previews,private PreviewStorage $storage,private PreviewLifecycle $lifecycle,#[Autowire('%env(MCP_PUBLIC_BASE_URL)%')]private string $publicBase, private \App\Favorites\Favorites $favorites){}

    public function actor():User
    {
        $identity=$this->security->getToken()?->getUser();
        if(!$identity instanceof ComposeIdentity||!$identity->owner->isEnabled())throw new CatalogueException(403,'Authenticated composer identity required.',[],'FORBIDDEN');
        return $identity->owner;
    }
    public function invoke(string $operation,array $input):array
    {
        $this->actor();
        return match($operation){
            'capabilities'=>$this->capabilities(), 'search_catalogue'=>$this->search($input), 'list_preview_themes'=>$this->themes($input),
            'get_reference'=>$this->reference($input), 'get'=>$this->get($input), 'get_node'=>$this->getNode($input), 'create_draft'=>$this->create($input),
            'update_metadata'=>$this->metadata($input), 'validate'=>$this->validate($input), 'get_history'=>$this->history($input), 'restore_revision'=>$this->restore($input),
            'render_preview'=>$this->preview($input), 'get_preview'=>$this->previewResult($input), 'cancel_preview'=>$this->cancelPreview($input),
            default=>$this->mutate($operation,$input),
        };
    }
    private function allowed(string $action,mixed $subject=null):void
    {
        if(!$this->auth->isGranted($action,$subject))throw new CatalogueException(403,'Composer scope or catalogue permission is insufficient.',[],'FORBIDDEN');
    }
    private function item(string $id):CatalogueItem
    {
        $item=$this->em->find(CatalogueItem::class,$id)??throw new CatalogueException(404,'Composition not found.',[],'NOT_FOUND');
        $this->workspace->require($item);return $item;
    }
    private function envelope(?CatalogueItem $item,string $summary,array $data=[]):array
    {
        return array_replace(['ok'=>true,'compositionId'=>$item?->getId(),'revision'=>$item?$this->writer->draft($item)->getRevision():null,'composerUrl'=>$item?rtrim($this->publicBase,'/').$this->urls->generate('composition_edit',['id'=>$item->getId()]):null,'summary'=>$summary,'warnings'=>[]],$data);
    }
    private function capabilities():array
    {
        $this->allowed('COMPOSE_READ');
        return $this->envelope(null,'Discover the existing composition editor.',[
            'creationKinds'=>['block','layout'],'nodeKinds'=>['container','reference','text'],'slots'=>['children'],
            'containerTags'=>['div','section','article','header','footer','main','aside','nav'],
            'limits'=>['documentBytes'=>65536,'nodes'=>500,'levels'=>20,'batchCommands'=>50,'pageSize'=>50],
            'commands'=>CompositionTree::COMMANDS,'saveSemantics'=>'Each MCP mutation saves one authoritative revision. A batch saves once. The manual browser tree remains unsaved until Save. All writes require expectedRevision and operationId; retries must use identical arguments.',
            'aliases'=>'Use alias: name on an add/duplicate command, then $name as a nodeId/parentId in later commands of the same batch. Aliases are not persisted node IDs.',
            'positions'=>'Zero-based child index; move_node position is measured after removing the source. Only containers accept children. Root move/duplicate/remove and outdent of direct children are forbidden.',
            'nesting'=>'Indent appends into the immediately preceding container sibling. Outdent inserts immediately after the parent in the grandparent. Move up/down swap adjacent siblings.',
            'referencePolicy'=>'Existing immutable releases only; exact version pins. Props are instance data checked against that release schema. No named slots or bindings exist in this version.',
            'unsupported'=>['component source authoring','Twig/JavaScript/PHP editing','publishing','duplicate-and-convert-to-source','theme/font administration','package installation','persistent renderer preferences'],
            'visualInspection'=>'No screenshot renderer is installed. Preview HTML/diagnostics and a sandboxed authenticated URL are available; this does not imply visual inspection.',
        ]);
    }
    private function search(array $input):array
    {
        $this->allowed('COMPOSE_READ');$query=trim($input['query']??'');$offset=$input['offset']??0;$limit=$input['limit']??20;$kind=$input['kind']??null;
        $builder=$this->em->getRepository(CatalogueItem::class)->createQueryBuilder('i')->where('i.kind IN (:kinds)')->setParameter('kinds',['component','block','layout'])->orderBy('i.namespace','ASC')->addOrderBy('i.slug','ASC');
        if($query!=='')$builder->andWhere('LOWER(CONCAT(i.namespace, :slash, i.slug, :space, i.title)) LIKE :q')->setParameter('slash','/')->setParameter('space',' ')->setParameter('q','%'.strtolower(str_replace(['%','_'],['\\%','\\_'],$query)).'%');
        if($kind)$builder->andWhere('i.kind = :kind')->setParameter('kind',$kind);
        if($input['favoritesOnly']??false)$builder->andWhere('EXISTS (SELECT f.id FROM App\\Entity\\Favorite f WHERE f.catalogueItem=i AND f.owner=:favoriteOwner)')->setParameter('favoriteOwner',$this->actor());
        $accessible=[];
        foreach($builder->getQuery()->toIterable() as $item){if(!$this->auth->isGranted('COMPOSE_READ',$item))continue;$accessible[]=$item;if(count($accessible)>$offset+$limit)break;}
        $results=[];
        foreach(array_slice($accessible,$offset,$limit) as $item){
            $draft=$this->writer->draft($item);$versions=[];
            foreach($this->em->getRepository(Release::class)->findBy(['item'=>$item],['createdAt'=>'DESC'],10) as $release)$versions[]=['releaseId'=>$release->getId(),'version'=>$release->getVersion()];
            $results[]=['id'=>$item->getId(),'favorite'=>$this->favorites->contains($item),'name'=>$item->getName(),'kind'=>$item->getKind(),'title'=>$item->getTitle(),'summary'=>mb_substr($item->getDescription(),0,300),'revision'=>$draft->getRevision(),'editor'=>isset($draft->getSourceMap()->{'meta/composition.json'})?'composition':'source','editableInComposer'=>isset($draft->getSourceMap()->{'meta/composition.json'}),'releases'=>$versions,'insertable'=>count($versions)>0];
        }
        return $this->envelope(null,'Accessible catalogue results. Draft creation and release insertion are different operations.',['items'=>$results,'offset'=>$offset,'nextOffset'=>count($accessible)>$offset+$limit?$offset+$limit:null]);
    }
    private function themes(array $input):array
    {
        $this->allowed('COMPOSE_READ');$offset=$input['offset']??0;$limit=$input['limit']??20;
        $rows=$this->em->getRepository(ThemeRelease::class)->findBy([],['createdAt'=>'DESC'],$limit+1,$offset);$items=[];
        foreach(array_slice($rows,0,$limit) as $r)$items[]=['id'=>$r->getId(),'name'=>$r->getTheme()->getName(),'version'=>$r->getVersion(),'mode'=>$r->getDocument()->colorScheme];
        return $this->envelope(null,'Existing published themes for composition previews.',['themes'=>$items,'nextOffset'=>count($rows)>$limit?$offset+$limit:null]);
    }
    private function reference(array $input):array
    {
        $this->allowed('COMPOSE_READ');$r=$this->workspace->release($input['name'],$input['version']);
        return $this->envelope(null,'Read the exact immutable reference schema and defaults.',['reference'=>$this->workspace->referenceDetails($r)]);
    }
    private function get(array $input):array
    {
        $item=$this->item($input['compositionId']);$draft=$this->writer->draft($item);$prepared=$this->workspace->prepare($item,$draft->getRevision(),$this->workspace->document($draft));
        return $this->envelope($item,'Read the saved composition.',['name'=>$item->getName(),'kind'=>$item->getKind(),'title'=>$item->getTitle(),'description'=>$item->getDescription(),'document'=>$prepared['document'],'nodes'=>$this->nodeIndex($prepared['document']),'references'=>$prepared['manifestData']->dependencies,'valid'=>true,'permissions'=>['edit'=>$this->auth->isGranted('COMPOSE_EDIT',$item),'preview'=>$this->auth->isGranted('COMPOSE_PREVIEW',$item)]]);
    }
    private function nodeIndex(\stdClass $document):array
    {
        $result=[];
        foreach($this->trees->index($document) as $id=>$e)$result[]=['id'=>$id,'type'=>$e['node']->type,'parentId'=>$e['parentId'],'position'=>$e['position'],'depth'=>$e['depth'],'childCount'=>count($e['node']->children??[])];
        return $result;
    }
    private function getNode(array $input):array
    {
        $item=$this->item($input['compositionId']);$draft=$this->writer->draft($item);$doc=$this->workspace->document($draft);$this->workspace->prepare($item,$draft->getRevision(),$doc);
        $index=$this->trees->index($doc);$entry=$index[$input['nodeId']]??throw new CatalogueException(404,'Node not found.',[],'NOT_FOUND');$node=$entry['node'];$schema=match($node->type){'container'=>['tag'=>['type'=>'string','enum'=>['div','section','article','header','footer','main','aside','nav']],'classes'=>['type'=>'array','items'=>['type'=>'string']]],'text'=>['value'=>['type'=>'string','maxLength'=>10000]],'reference'=>$this->workspace->referenceDetails($this->workspace->release($node->name,$node->version))};
        $count=0;$truncated=false;$max=$input['maxNodes']??50;$depth=$input['depth']??1;
        $copy=function(object $n,int $level)use(&$copy,&$count,&$truncated,$max,$depth):object{$n=clone $n;$count++;if(isset($n->children)){$children=[];foreach($n->children as $child){if($level>=$depth||$count>=$max){$truncated=true;break;}$children[]=$copy($child,$level+1);}$n->children=$children;}return $n;};
        return $this->envelope($item,'Read one bounded subtree and its editable contract.',['node'=>$copy($node,1),'parentId'=>$entry['parentId'],'position'=>$entry['position'],'slot'=>$entry['parentId']?'children':null,'editable'=>$schema,'truncated'=>$truncated,'returnedNodes'=>$count,'root'=>$entry['parentId']===null,'allowedActions'=>$this->actions($entry,$index)]);
    }
    private function actions(array $entry,array $index):array
    {
        $actions=['update_node'];if($entry['node']->type==='container')$actions=[...$actions,'add_container','add_text','add_reference'];
        if($entry['parentId']!==null){$actions=[...$actions,'duplicate_node','remove_node','move_node'];$siblings=$index[$entry['parentId']]['node']->children;$i=$entry['position'];if($i>0)$actions[]='move_up';if($i<count($siblings)-1)$actions[]='move_down';if($i>0&&$siblings[$i-1]->type==='container')$actions[]='indent_node';if($entry['depth']>2)$actions[]='outdent_node';}return $actions;
    }
    private function create(array $input):array
    {
        $actor=$this->actor();$name=$input['name'];$this->allowed('COMPOSE_CREATE',$name);
        if(!in_array($input['kind'],['block','layout'],true))throw new CatalogueException(422,'Components use the source editor. Only block/layout drafts can be created through this composer.',[],'UNSUPPORTED_EDITOR');
        return $this->operations->run($actor,'create_draft',$name,$input['operationId'],$input,fn()=>$this->allowed('COMPOSE_CREATE',$name),function()use($input,$actor):array{
            $draft=$this->writer->create(new CreateItemInput($input['name'],$input['kind'],$input['title'],$input['description']??''),$actor);$this->workspace->capture($draft,$actor,'Created composition draft');
            return $this->envelope($draft->getItem(),'Created a composition-owned '.$input['kind'].' draft.',['document'=>$this->workspace->document($draft),'affectedNodeIds'=>[$this->workspace->document($draft)->root->id]]);
        });
    }
    private function authorizeWrite(CatalogueItem $item):void
    {
        $draft=$this->workspace->require($item,'COMPOSE_EDIT');$this->em->refresh($draft);$this->workspace->prepare($item,$draft->getRevision(),$this->workspace->document($draft));
    }
    private function authorizeReplay(CatalogueItem $item,array $result):void
    {
        $draft=$this->workspace->require($item,'COMPOSE_EDIT');$this->em->refresh($draft);
        if(($result['document']??null) instanceof \stdClass){[$manifest,$sources]=$this->compiler->materialize($draft->getManifestData(),$draft->getSourceMap(),$result['document']);$this->workspace->authorizeReferences($manifest,$sources);}
    }
    private function mutate(string $operation,array $input):array
    {
        $item=$this->item($input['compositionId']);
        return $this->operations->run($this->actor(),$operation,$item->getId(),$input['operationId'],$input,fn()=>$this->authorizeWrite($item),function()use($operation,$input,$item):array{
            $draft=$this->writer->draft($item);$this->workspace->expected($draft,$input['expectedRevision']);
            if($operation==='apply_batch')$commands=$input['commands'];
            else{$command=$input;unset($command['compositionId'],$command['expectedRevision'],$command['operationId']);$commands=[(object)(['type'=>$operation]+$command)];}
            $commands=array_map(static fn($c)=>$c instanceof \stdClass?$c:(object)$c,$commands);
            $result=$this->workspace->commands($item,$input['expectedRevision'],$this->workspace->document($draft),$commands);
            $saved=$this->workspace->save($item,$input['expectedRevision'],$result['document'],$this->actor(),summary:$operation==='apply_batch'?'Applied '.count($commands).' composer commands':str_replace('_',' ',$operation));
            return $this->envelope($item,'Saved revision '.$saved->getRevision().'.',['affectedNodeIds'=>$result['affectedNodeIds'],'selectedNodeId'=>$result['selectedNodeId'],'aliases'=>$result['aliases'],'document'=>$this->workspace->document($saved)]);
        },fn($result)=>$this->authorizeReplay($item,$result));
    }
    private function metadata(array $input):array
    {
        $item=$this->item($input['compositionId']);
        return $this->operations->run($this->actor(),'update_metadata',$item->getId(),$input['operationId'],$input,fn()=>$this->authorizeWrite($item),function()use($item,$input):array{
            $draft=$this->writer->draft($item);$saved=$this->workspace->save($item,$input['expectedRevision'],$this->workspace->document($draft),$this->actor(),$input['title'],$input['description']??null,'Updated composition metadata');
            return $this->envelope($item,'Saved composition metadata.',['title'=>$item->getTitle(),'description'=>$item->getDescription()]);
        });
    }
    private function validate(array $input):array
    {
        $item=$this->item($input['compositionId']);$draft=$this->writer->draft($item);$this->workspace->expected($draft,$input['revision']);$prepared=$this->workspace->prepare($item,$input['revision'],$this->workspace->document($draft));
        return $this->envelope($item,'Composition and immutable dependency closure are valid.',['valid'=>true,'references'=>$prepared['manifestData']->dependencies,'nodeCount'=>count($this->trees->index($prepared['document']))]);
    }
    private function history(array $input):array
    {
        $item=$this->item($input['compositionId']);
        return $this->envelope($item,'Recoverable saved composition revisions.',$this->historyService->page($item,$input['offset']??0,$input['limit']??20));
    }
    private function restore(array $input):array
    {
        $item=$this->item($input['compositionId']);
        return $this->operations->run($this->actor(),'restore_revision',$item->getId(),$input['operationId'],$input,fn()=>$this->authorizeWrite($item),function()use($input,$item):array{
            $saved=$this->historyService->restore($item,$input['expectedRevision'],$input['restoreRevision'],$this->actor());
            return $this->envelope($item,'Restored revision '.$input['restoreRevision'].' as new revision '.$saved->getRevision().'.',['document'=>$this->workspace->document($saved),'restoredFrom'=>$input['restoreRevision']]);
        },fn($result)=>$this->authorizeReplay($item,$result));
    }
    private function preview(array $input):array
    {
        $item=$this->item($input['compositionId']);$this->allowed('COMPOSE_PREVIEW',$item);
        return $this->operations->run($this->actor(),'render_preview',$item->getId(),$input['operationId'],$input,fn()=>$this->allowed('COMPOSE_PREVIEW',$item),function()use($item,$input):array{
            $draft=$this->writer->draft($item);$this->workspace->expected($draft,$input['revision']);$prepared=$this->workspace->prepare($item,$input['revision'],$this->workspace->document($draft));
            $theme=null;$themeId=$input['themeReleaseId']??null;if($themeId)$theme=($this->em->find(ThemeRelease::class,$themeId)??throw new CatalogueException(404,'Published theme not found.',[],'NOT_FOUND'))->getDocument();
            $settings=new RendererSettings(width:$input['width']??768,rootSize:$theme?->typography->rootFontSizePx??16,bodyFont:$theme?'theme':'system-sans',headingFont:$theme?'theme':'system-sans',textClass:$theme?->typography->textClass??'text-base',scripts:$input['scripts']??true,themeReleaseId:$themeId);
            $job=$this->previews->create(new PreviewInput($item->getId(),$input['revision'],bin2hex(random_bytes(16)),$prepared['manifestData'],$prepared['sourceMap'],preset:$settings),$this->actor());
            $this->em->persist(new CompositionPreview($job,$item,$input['revision']));
            return $this->envelope($item,'Queued an isolated preview of exactly revision '.$input['revision'].'.',['jobId'=>$job->getId(),'status'=>$job->getStatus(),'previewRevision'=>$input['revision'],'previewUrl'=>rtrim($this->publicBase,'/').$this->urls->generate('composition_preview_page',['id'=>$job->getId()]),'visualInspectionAvailable'=>false]);
        },fn($result)=>$this->binding($result['jobId']));
    }
    private function binding(string $id):CompositionPreview
    {
        $binding=$this->em->find(CompositionPreview::class,$id);
        if(!$binding||$binding->getJob()->getOwner()->getId()!==$this->actor()->getId())throw new CatalogueException(404,'Owned composition preview not found.',[],'NOT_FOUND');
        $item=$binding->getItem();$this->allowed('COMPOSE_PREVIEW',$item);$draft=$this->workspace->require($item);
        $payload=$binding->getJob()->getPayload();
        // The exact captured tree is in its source map; reauthorize every pinned reference
        // against the current credential before returning even a previously rendered result.
        $sourcePath='metadata/'.$item->getName().'/composition.json';
        if(isset($payload['sources'][$sourcePath])){
            $doc=StrictJson::decode($payload['sources'][$sourcePath],65536,64);
            [$manifest,$sources]=$this->compiler->materialize($draft->getManifestData(),$draft->getSourceMap(),$doc);$this->workspace->authorizeReferences($manifest,$sources);
        }else{throw new CatalogueException(409,'The captured composition tree is unavailable; create a fresh preview.',[],'VALIDATION_FAILED');}
        return $binding;
    }
    private function previewResult(array $input):array
    {
        $binding=$this->binding($input['jobId']);$job=$binding->getJob();$data=['jobId'=>$job->getId(),'previewRevision'=>$binding->getRevision(),'status'=>$job->getStatus(),'errors'=>$job->getError(),'visualInspectionAvailable'=>false,'previewUrl'=>rtrim($this->publicBase,'/').$this->urls->generate('composition_preview_page',['id'=>$job->getId()])];
        if(($input['includeHtml']??false)&&$job->getStatus()==='succeeded')$data['html']=$this->storage->read($job->getId(),$job->getRunToken())['html'];
        return $this->envelope($binding->getItem(),'Read the exact captured preview revision.',$data);
    }
    private function cancelPreview(array $input):array
    {
        $binding=$this->binding($input['jobId']);
        return $this->operations->run($this->actor(),'cancel_preview',$input['jobId'],$input['operationId'],$input,fn()=>$this->binding($input['jobId']),function()use($binding,$input):array{
            $this->lifecycle->cancel($binding->getJob());return $this->envelope($binding->getItem(),'Cancelled the owned preview job.',['jobId'=>$input['jobId'],'status'=>$binding->getJob()->getStatus(),'previewRevision'=>$binding->getRevision()]);
        });
    }
}
