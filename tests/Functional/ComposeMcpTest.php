<?php
namespace App\Tests\Functional;
use App\Catalogue\{CatalogueWriter,CreateItemInput};
use App\Entity\ComposeToken;
use App\Mcp\Security\ComposeTokens;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Ulid;

final class ComposeMcpTest extends DatabaseTestCase
{
    private int $sequence=0;
    private array $headers=[];
    private function connect(KernelBrowser $client,string $plain):void
    {
        $this->headers=['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream','HTTP_AUTHORIZATION'=>'Bearer '.$plain];
        $answer=$this->rpc($client,'initialize',['protocolVersion'=>'2025-11-25','capabilities'=>(object)[],'clientInfo'=>['name'=>'studio-tests','version'=>'1']]);
        self::assertSame('2025-11-25',$answer['result']['protocolVersion']??null,json_encode($answer));
        $this->headers['HTTP_MCP_SESSION_ID']=$client->getResponse()->headers->get('Mcp-Session-Id');$this->headers['HTTP_MCP_PROTOCOL_VERSION']='2025-11-25';
        $this->rpc($client,'notifications/initialized',(object)[],false);
    }
    private function rpc(KernelBrowser $client,string $method,mixed $params,bool $request=true):array
    {
        $body=['jsonrpc'=>'2.0','method'=>$method,'params'=>$params];if($request)$body['id']=++$this->sequence;
        $client->request('POST','/mcp/compose',server:$this->headers,content:json_encode($body,JSON_THROW_ON_ERROR));
        $response=$client->getResponse();$content=$response->getContent();
        if($content===false){ob_start();$response->sendContent();$content=ob_get_clean();}
        if(!$request)return [];
        if(str_starts_with($response->headers->get('Content-Type',''),'text/event-stream')){preg_match_all('/^data: (.+)$/m',$content,$matches);$content=end($matches[1]);}
        return json_decode($content,true,64,JSON_THROW_ON_ERROR);
    }
    private function call(KernelBrowser $client,string $name,array $arguments=[]):array
    {
        $response=$this->rpc($client,'tools/call',['name'=>'compose_'.$name,'arguments'=>$arguments?:new \stdClass()]);
        self::assertArrayNotHasKey('error',$response,json_encode($response));return $response['result'];
    }
    private function success(KernelBrowser $client,string $name,array $arguments=[]):array
    {
        $r=$this->call($client,$name,$arguments);self::assertFalse($r['isError']??false,json_encode($r));return $r['structuredContent'];
    }
    public function testInstancePropertyParityPreservesOmissionFalsyStructuredValuesAndNodeScopedErrors():void
    {
        $client=self::createClient();$owner=$this->account();$this->signIn($client,$owner);
        $writer=self::getContainer()->get(CatalogueWriter::class);
        $component=$writer->create(new CreateItemInput('mcp-tests/props-'.strtolower((string)new Ulid()),'component','Instance property schema'),$owner);
        $m=$component->getManifestData();$sources=$component->getSourceMap();
        $fixture=json_decode(file_get_contents(dirname(__DIR__).'/Browser/fixtures/instance-properties.json'));
        $schema=(object)['type'=>'object','$defs'=>$fixture->{'$defs'},'properties'=>$fixture->properties,'required'=>['message',...$fixture->required],'additionalProperties'=>true];
        $m->defaults=(object)array_merge((array)$m->defaults,(array)$fixture->defaults);
        $sources->{'meta/props.schema.json'}=json_encode($schema,JSON_THROW_ON_ERROR);
        $sources->{'meta/examples.json'}=json_encode([(object)['name'=>'Edge values','props'=>$m->defaults]],JSON_THROW_ON_ERROR);
        $writer->save($component->getItem(),1,new \App\Catalogue\DraftInput($m,$sources,'Instance property schema'),$owner);
        $release=self::getContainer()->get(\App\Packaging\ReleasePublisher::class)->publish($component->getItem(),new \App\Packaging\PublishInput('1.0.0',2));
        $layout=$writer->create(new CreateItemInput('mcp-tests/manual-props-'.strtolower((string)new Ulid()),'layout','Manual props'),$owner);
        $workspace=self::getContainer()->get(\App\Composition\CompositionWorkspace::class);$tree=$workspace->document($layout);
        $props=clone $m->defaults;unset($props->releaseGuide);$props->message='Only this instance';$props->count=0;$props->ratio=0;$props->nullable=null;$props->empty='';$props->unknown=(object)['flag'=>false,'nil'=>null,'zero'=>0,'nested'=>[(object)['value'=>'']]];
        $client->request('GET','/api/v1/csrf');$csrf=json_decode($client->getResponse()->getContent())->token;
        $url='/api/v1/items/'.$layout->getItem()->getId().'/composition';
        $client->jsonRequest('POST',$url.'/commands',['document'=>$tree,'expectedRevision'=>1,'commands'=>[(object)['type'=>'add_reference','selectedId'=>$tree->root->id,'name'=>$component->getItem()->getName(),'version'=>'1.0.0','props'=>$props]]],['HTTP_X_CSRF_TOKEN'=>$csrf]);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());$manual=json_decode($client->getResponse()->getContent())->document;
        $client->jsonRequest('PUT',$url,['document'=>$manual,'expectedRevision'=>1],['HTTP_X_CSRF_TOKEN'=>$csrf]);self::assertResponseIsSuccessful();
        $client->request('GET',$url);$persisted=json_decode($client->getResponse()->getContent())->document->root->children[0]->props;
        $json=new \Styleguide\Contracts\CanonicalJson();self::assertSame($json->encode($props),$json->encode($persisted));self::assertFalse(property_exists($persisted,'optionalGuide'));self::assertFalse(property_exists($persisted,'releaseGuide'));self::assertNull($persisted->nullable);self::assertFalse($persisted->unknown->flag);self::assertSame(0,$persisted->count);
        $invalid=json_decode(json_encode($manual));$invalid->root->children[0]->props->count=11;
        $client->jsonRequest('POST',$url,['document'=>$invalid,'expectedRevision'=>2],['HTTP_X_CSRF_TOKEN'=>$csrf]);self::assertResponseStatusCodeSame(422);
        $errors=json_decode($client->getResponse()->getContent(),true)['fieldErrors'];self::assertArrayHasKey('nodes.'.$manual->root->children[0]->id.'.props/count',$errors);
        $em=self::getContainer()->get(EntityManagerInterface::class);$owner=$em->find(\App\Entity\User::class,$owner->getId());[, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Property parity',ComposeTokens::SCOPES,['mcp-tests/*']);$this->connect($client,$plain);
        $created=$this->success($client,'create_draft',['name'=>'mcp-tests/mcp-props-'.strtolower((string)new Ulid()),'kind'=>'layout','title'=>'MCP props','operationId'=>'props-create']);
        $added=$this->success($client,'add_reference',['compositionId'=>$created['compositionId'],'expectedRevision'=>1,'operationId'=>'props-add','parentId'=>$created['document']['root']['id'],'position'=>0,'name'=>$component->getItem()->getName(),'version'=>'1.0.0','props'=>$props]);
        $actual=$this->success($client,'get',['compositionId'=>$created['compositionId']]);self::assertSame($json->encode($props),$json->encode(json_decode(json_encode($actual['document']['root']['children'][0]['props']))));
        $bad=clone $props;$bad->count=11;$error=$this->call($client,'update_node',['compositionId'=>$created['compositionId'],'expectedRevision'=>2,'operationId'=>'props-bad','nodeId'=>$added['selectedNodeId'],'changes'=>(object)['props'=>$bad]]);self::assertTrue($error['isError']);self::assertArrayHasKey('nodes.'.$added['selectedNodeId'].'.props/count',$error['structuredContent']['details']);
        self::assertSame(2,$this->success($client,'get',['compositionId'=>$created['compositionId']])['revision']);
        self::assertSame('Your component is ready.',$em->find(\App\Entity\Release::class,$release->getId())->getManifestData()->defaults->message);
    }

    public function testDiscoveryAndRealCommandsBatchesRetriesHistoryAndManualConflicts():void
    {
        $client=self::createClient();$owner=$this->account();[$token,$plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'MCP test',ComposeTokens::SCOPES,['mcp-tests/*']);
        $this->connect($client,$plain);
        $list=$this->rpc($client,'tools/list',(object)[]);$names=array_column($list['result']['tools']??[],'name');self::assertCount(26,$names);foreach($names as $name)self::assertStringStartsWith('compose_',$name);
        foreach($list['result']['tools'] as $tool){self::assertArrayHasKey('inputSchema',$tool);self::assertArrayHasKey('outputSchema',$tool);self::assertFalse($tool['annotations']['openWorldHint']);}
        $name='mcp-tests/layout-'.strtolower((string)new Ulid());$create=['name'=>$name,'kind'=>'layout','title'=>'MCP layout','operationId'=>'create-operation-1'];
        $new=$this->success($client,'create_draft',$create);self::assertSame(1,$new['revision']);$id=$new['compositionId'];$root=$new['document']['root']['id'];
        $replay=$this->success($client,'create_draft',$create);self::assertSame($id,$replay['compositionId']);self::assertTrue($replay['replayed']);
        $batch=['compositionId'=>$id,'expectedRevision'=>1,'operationId'=>'batch-operation-1','commands'=>[(object)['type'=>'add_container','parentId'=>$root,'position'=>0,'alias'=>'group'],(object)['type'=>'add_text','parentId'=>'$group','position'=>0,'value'=>'{{ 7*7 }} <b>literal</b>','alias'=>'text'],(object)['type'=>'add_text','parentId'=>$root,'position'=>1,'value'=>'Second','alias'=>'second']]];
        $edited=$this->success($client,'apply_batch',$batch);self::assertSame(2,$edited['revision']);self::assertCount(3,$edited['aliases']);
        $again=$this->success($client,'apply_batch',$batch);self::assertSame(2,$again['revision']);self::assertTrue($again['replayed']);self::assertEquals($edited['document'],$again['document']);
        $params=['compositionId'=>$id,'expectedRevision'=>2,'operationId'=>'move-operation-1','nodeId'=>$edited['aliases']['second']];
        $nested=$this->success($client,'indent_node',$params);self::assertSame(3,$nested['revision']);self::assertCount(2,$nested['document']['root']['children'][0]['children']);
        $params['expectedRevision']=3;$params['operationId']='outdent-operation-1';$out=$this->success($client,'outdent_node',$params);self::assertSame(4,$out['revision']);
        $invalid=$this->call($client,'move_node',['compositionId'=>$id,'expectedRevision'=>4,'operationId'=>'cycle-operation-1','nodeId'=>$edited['aliases']['group'],'parentId'=>$edited['aliases']['group'],'position'=>0]);self::assertTrue($invalid['isError']);self::assertSame('REFERENCE_CYCLE',$invalid['structuredContent']['code']);
        $current=$this->success($client,'get',['compositionId'=>$id]);self::assertSame(4,$current['revision']);
        $bad=$batch;$bad['expectedRevision']=4;$bad['operationId']='bad-batch-operation';$bad['commands']=[(object)['type'=>'add_text','parentId'=>$root,'position'=>2,'value'=>'Must rollback'],(object)['type'=>'remove_node','nodeId'=>$root]];
        $failed=$this->call($client,'apply_batch',$bad);self::assertTrue($failed['isError']);self::assertSame($current['document'],$this->success($client,'get',['compositionId'=>$id])['document']);
        $restored=$this->success($client,'restore_revision',['compositionId'=>$id,'expectedRevision'=>4,'restoreRevision'=>1,'operationId'=>'restore-operation-1']);self::assertSame(5,$restored['revision']);self::assertSame([],$restored['document']['root']['children']);
        $history=$this->success($client,'get_history',['compositionId'=>$id]);self::assertCount(5,$history['history']);
        $stale=$this->call($client,'remove_node',['compositionId'=>$id,'expectedRevision'=>4,'nodeId'=>$edited['aliases']['second'],'operationId'=>'stale-operation-1']);self::assertTrue($stale['isError']);self::assertSame('REVISION_CONFLICT',$stale['structuredContent']['code']);
        $metadata=$this->success($client,'update_metadata',['compositionId'=>$id,'expectedRevision'=>5,'operationId'=>'metadata-operation-1','title'=>'Renamed in MCP','description'=>'Shared metadata']);self::assertSame(6,$metadata['revision']);self::assertSame('Renamed in MCP',$metadata['title']);
        // The normal browser opens the same persisted tree; stale browser writes also fail.
        $this->signIn($client,self::getContainer()->get(EntityManagerInterface::class)->find(\App\Entity\User::class,$owner->getId()));$client->request('GET','/catalogue/'.$id.'/compose');self::assertResponseIsSuccessful();self::assertSelectorExists('[data-composition-revision-value="6"]');self::assertSelectorExists('iframe[sandbox="allow-scripts"]');
        $client->request('GET','/api/v1/csrf');$csrf=json_decode($client->getResponse()->getContent())->token;
        $client->jsonRequest('PUT','/api/v1/items/'.$id.'/composition',['document'=>$current['document'],'expectedRevision'=>4],['HTTP_X_CSRF_TOKEN'=>$csrf]);self::assertResponseStatusCodeSame(412);
    }
    public function testTokensScopesSessionsAndSourceEditorBoundaries():void
    {
        $client=self::createClient();$client->request('POST','/mcp/compose',server:['CONTENT_TYPE'=>'application/json'],content:'{}');self::assertResponseStatusCodeSame(401);self::assertStringContainsString('resource_metadata',$client->getResponse()->headers->get('WWW-Authenticate'));
        $owner=$this->account();[$token,$plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Read only',['compose:read'],['mcp-tests/*']);$this->connect($client,$plain);
        $no=$this->call($client,'create_draft',['name'=>'mcp-tests/denied','kind'=>'block','title'=>'Denied','operationId'=>'denied-create-1']);self::assertTrue($no['isError']);self::assertSame('FORBIDDEN',$no['structuredContent']['code']);
        $other=$this->account();[, $otherPlain]=self::getContainer()->get(ComposeTokens::class)->issue($other,'Other reader',['compose:read'],['mcp-tests/*']);$originalHeaders=$this->headers;$this->headers['HTTP_AUTHORIZATION']='Bearer '.$otherPlain;$this->rpc($client,'tools/list',(object)[]);self::assertResponseStatusCodeSame(404);
        $this->headers=$originalHeaders;$em=self::getContainer()->get(EntityManagerInterface::class);$record=$em->find(ComposeToken::class,$token->getId());$record->revoke();$em->flush();$this->rpc($client,'tools/list',(object)[]);self::assertResponseStatusCodeSame(401);
        $em=self::getContainer()->get(EntityManagerInterface::class);
        [, $valid]=self::getContainer()->get(ComposeTokens::class)->issue($em->find(\App\Entity\User::class,$owner->getId()),'Editor',ComposeTokens::SCOPES,['mcp-tests/*']);$this->connect($client,$valid);
        $em=self::getContainer()->get(EntityManagerInterface::class);
        $source=self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('mcp-tests/source-'.strtolower((string)new Ulid()),'component','Source'),$em->find(\App\Entity\User::class,$owner->getId()));
        $error=$this->call($client,'get',['compositionId'=>$source->getItem()->getId()]);self::assertTrue($error['isError']);self::assertSame('UNSUPPORTED_EDITOR',$error['structuredContent']['code']);
        $client->request('POST','/mcp/compose',server:['HTTP_HOST'=>'evil.example','CONTENT_TYPE'=>'application/json'],content:'{}');self::assertResponseStatusCodeSame(403);
    }
    public function testReferenceSchemasTransitiveScopesAndReplayReauthorization():void
    {
        $client=self::createClient();$owner=$this->account();$ownerId=$owner->getId();
        $writer=self::getContainer()->get(CatalogueWriter::class);$publisher=self::getContainer()->get(\App\Packaging\ReleasePublisher::class);
        $name='private-ref/leaf-'.strtolower((string)new Ulid());$leaf=$writer->create(new CreateItemInput($name,'component','Private reference'),$owner);$publisher->publish($leaf->getItem(),new \App\Packaging\PublishInput('1.0.0',1));
        $block=$writer->create(new CreateItemInput('mcp-tests/block-'.strtolower((string)new Ulid()),'block','Nested block'),$owner);
        $tree=(object)['schemaVersion'=>1,'root'=>(object)['type'=>'reference','name'=>$name,'version'=>'1.0.0','props'=>(object)['message'=>'Nested']]];
        [$manifest,$sources]=self::getContainer()->get(\App\Composition\CompositionCompiler::class)->materialize($block->getManifestData(),$block->getSourceMap(),$tree);
        $writer->save($block->getItem(),1,new \App\Catalogue\DraftInput($manifest,$sources,'Nested block'),$owner);$publisher->publish($block->getItem(),new \App\Packaging\PublishInput('1.0.0',2));$blockName=$block->getItem()->getName();
        [, $wide]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'All references',ComposeTokens::SCOPES,['*']);$this->connect($client,$wide);
        $created=$this->success($client,'create_draft',['name'=>'mcp-tests/ref-'.strtolower((string)new Ulid()),'kind'=>'layout','title'=>'Reference checks','operationId'=>'create-ref-operation']);$id=$created['compositionId'];$root=$created['document']['root']['id'];
        $request=['compositionId'=>$id,'expectedRevision'=>1,'operationId'=>'add-ref-operation','parentId'=>$root,'position'=>0,'name'=>$name,'version'=>'1.0.0'];
        $bad=$request;$bad['props']=(object)['message'=>42];$failed=$this->call($client,'add_reference',$bad);self::assertTrue($failed['isError']);self::assertSame('VALIDATION_FAILED',$failed['structuredContent']['code']);self::assertSame(1,$this->success($client,'get',['compositionId'=>$id])['revision']);
        $added=$this->success($client,'add_reference',$request);$refId=$added['selectedNodeId'];self::assertSame('1.0.0',$added['document']['root']['children'][0]['version']);self::assertIsString($added['document']['root']['children'][0]['props']['message']);
        $changed=$this->success($client,'update_node',['compositionId'=>$id,'expectedRevision'=>2,'operationId'=>'update-ref-operation','nodeId'=>$refId,'changes'=>(object)['props'=>(object)['message'=>'Only this instance']]]);self::assertSame(3,$changed['revision']);
        $schema=$this->success($client,'get_node',['compositionId'=>$id,'nodeId'=>$refId]);self::assertSame('string',$schema['editable']['propsSchema']['properties']['message']['type']);self::assertNotSame('Only this instance',$schema['editable']['props']['message']);
        $reused=$request;$reused['position']=1;$conflict=$this->call($client,'add_reference',$reused);self::assertSame('IDEMPOTENCY_CONFLICT',$conflict['structuredContent']['code']);
        $this->success($client,'remove_node',['compositionId'=>$id,'expectedRevision'=>3,'operationId'=>'remove-ref-operation','nodeId'=>$refId]);
        $em=self::getContainer()->get(EntityManagerInterface::class);[, $narrow]=self::getContainer()->get(ComposeTokens::class)->issue($em->find(\App\Entity\User::class,$ownerId),'Restricted references',ComposeTokens::SCOPES,['mcp-tests/*']);$this->connect($client,$narrow);
        $replay=$this->call($client,'add_reference',$request);self::assertTrue($replay['isError']);self::assertSame('NOT_FOUND',$replay['structuredContent']['code']);
        $nested=$request;$nested['name']=$blockName;$nested['expectedRevision']=4;$nested['operationId']='nested-ref-operation';$error=$this->call($client,'add_reference',$nested);self::assertTrue($error['isError']);self::assertSame('NOT_FOUND',$error['structuredContent']['code']);self::assertSame(4,$this->success($client,'get',['compositionId'=>$id])['revision']);
        $self=$nested;
        $current=$this->success($client,'get',['compositionId'=>$id]);$self['name']=$current['name'];$self['operationId']='self-ref-operation';$error=$this->call($client,'add_reference',$self);self::assertTrue($error['isError']);self::assertSame('REFERENCE_CYCLE',$error['structuredContent']['code']);self::assertSame(4,$this->success($client,'get',['compositionId'=>$id])['revision']);
    }
    public function testExpiredDisabledAndReaderCredentialsNeverAuthorize():void
    {
        $client=self::createClient();$owner=$this->account();$em=self::getContainer()->get(EntityManagerInterface::class);$plain='sgc_'.bin2hex(random_bytes(32));$expired=new ComposeToken($owner,'Expired',hash('sha256',$plain),ComposeTokens::SCOPES,['*'],new \DateTimeImmutable('-1 minute'));$em->persist($expired);$em->flush();
        $this->headers=['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream','HTTP_AUTHORIZATION'=>'Bearer '.$plain];$this->rpc($client,'initialize',(object)[]);self::assertResponseStatusCodeSame(401);
        $em=self::getContainer()->get(EntityManagerInterface::class);$owner=$em->find(\App\Entity\User::class,$owner->getId());[, $disabled]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Disabled next',ComposeTokens::SCOPES,['*']);$owner->disable();$em->flush();$this->headers['HTTP_AUTHORIZATION']='Bearer '.$disabled;$this->rpc($client,'initialize',(object)[]);self::assertResponseStatusCodeSame(401);
        $reader=$this->account('ROLE_READER');$em=self::getContainer()->get(EntityManagerInterface::class);$readerPlain='sgc_'.bin2hex(random_bytes(32));$readerToken=new ComposeToken($reader,'Reader',hash('sha256',$readerPlain),ComposeTokens::SCOPES,['*'],new \DateTimeImmutable('+1 day'));$em->persist($readerToken);$em->flush();$this->headers['HTTP_AUTHORIZATION']='Bearer '.$readerPlain;$this->rpc($client,'initialize',(object)[]);self::assertResponseStatusCodeSame(401);
        $client->request('POST','/mcp/compose',server:['HTTP_ORIGIN'=>'https://evil.example.test','CONTENT_TYPE'=>'application/json'],content:'{}');self::assertResponseStatusCodeSame(403);
        $client->request('POST','/mcp/compose',server:['CONTENT_TYPE'=>'application/json'],content:str_repeat('x',262145));self::assertResponseStatusCodeSame(413);
    }

    public function testPreviewRevisionSnapshotOwnedStatusAndIdempotentCancellation():void
    {
        $client=self::createClient();$owner=$this->account();[, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Preview owner',ComposeTokens::SCOPES,['mcp-tests/*']);$this->connect($client,$plain);
        $created=$this->success($client,'create_draft',['name'=>'mcp-tests/preview-'.strtolower((string)new Ulid()),'kind'=>'layout','title'=>'Captured preview','operationId'=>'create-preview-operation']);$id=$created['compositionId'];$root=$created['document']['root']['id'];
        $queued=$this->success($client,'render_preview',['compositionId'=>$id,'revision'=>1,'operationId'=>'queue-preview-operation','width'=>375,'scripts'=>false]);self::assertSame(1,$queued['previewRevision']);self::assertContains($queued['status'],['queued','running','succeeded']);
        $this->success($client,'add_text',['compositionId'=>$id,'expectedRevision'=>1,'operationId'=>'after-preview-operation','parentId'=>$root,'position'=>0,'value'=>'Saved after captured revision']);
        $status=$this->success($client,'get_preview',['jobId'=>$queued['jobId']]);self::assertSame(2,$status['revision']);self::assertSame(1,$status['previewRevision']);self::assertFalse($status['visualInspectionAvailable']);
        $cancel=['jobId'=>$queued['jobId'],'operationId'=>'cancel-preview-operation'];$cancelled=$this->success($client,'cancel_preview',$cancel);$expectedStatus=in_array($status['status'],['queued','running'],true)?'cancelled':$status['status'];self::assertSame($expectedStatus,$cancelled['status']);$again=$this->success($client,'cancel_preview',$cancel);self::assertTrue($again['replayed']);self::assertSame($expectedStatus,$again['status']);
        $this->signIn($client,self::getContainer()->get(EntityManagerInterface::class)->find(\App\Entity\User::class,$owner->getId()));$client->request('GET',parse_url($queued['previewUrl'],PHP_URL_PATH));self::assertResponseIsSuccessful();self::assertSelectorExists('iframe[sandbox=""]');
        $other=$this->account();[, $otherPlain]=self::getContainer()->get(ComposeTokens::class)->issue($other,'Other preview actor',ComposeTokens::SCOPES,['mcp-tests/*']);$this->connect($client,$otherPlain);$error=$this->call($client,'get_preview',['jobId'=>$queued['jobId']]);self::assertTrue($error['isError']);self::assertSame('NOT_FOUND',$error['structuredContent']['code']);
    }

    public function testPersonalFavoritesSearchRespectsOwnerScopesAndPagination():void
    {
        $client=self::createClient();$owner=$this->account();$other=$this->account();
        $writer=self::getContainer()->get(CatalogueWriter::class);$em=self::getContainer()->get(EntityManagerInterface::class);
        $prefix='favorites-'.strtolower((string)new Ulid());$ids=[];
        foreach(['a','b','c'] as $suffix){$item=$writer->create(new CreateItemInput('mcp-tests/'.$prefix.'-'.$suffix,'component','MCP favorite'),$owner)->getItem();$ids[]=$item->getId();$em->persist(new \App\Entity\Favorite($owner,$item));}
        $hidden=$writer->create(new CreateItemInput('hidden-tests/'.$prefix,'block','Private to a different credential scope'),$owner)->getItem();$em->persist(new \App\Entity\Favorite($owner,$hidden));
        $otherItem=$writer->create(new CreateItemInput('mcp-tests/'.$prefix.'-other','layout','Other user favorite'),$other)->getItem();$em->persist(new \App\Entity\Favorite($other,$otherItem));$em->flush();
        [, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Favorite search',['compose:read'],['mcp-tests/*']);$this->connect($client,$plain);
        $first=$this->success($client,'search_catalogue',['query'=>$prefix,'favoritesOnly'=>true,'limit'=>2]);
        self::assertSame(array_slice($ids,0,2),array_column($first['items'],'id'));self::assertSame(2,$first['nextOffset']);
        foreach($first['items'] as $item){self::assertTrue($item['favorite']);self::assertFalse($item['insertable']);}
        $second=$this->success($client,'search_catalogue',['query'=>$prefix,'favoritesOnly'=>true,'offset'=>2,'limit'=>2]);self::assertSame([$ids[2]],array_column($second['items'],'id'));self::assertNull($second['nextOffset']);
        $all=$this->success($client,'search_catalogue',['query'=>$prefix]);self::assertCount(4,$all['items']);self::assertFalse($all['items'][3]['favorite']);
        $kind=$this->success($client,'search_catalogue',['query'=>$prefix,'favoritesOnly'=>true,'kind'=>'layout']);self::assertSame([],$kind['items']);
        $em=self::getContainer()->get(EntityManagerInterface::class);$other=$em->find(\App\Entity\User::class,$other->getId());
        [, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($other,'Other favorites',['compose:read'],['mcp-tests/*']);$this->connect($client,$plain);
        $own=$this->success($client,'search_catalogue',['query'=>$prefix,'favoritesOnly'=>true]);self::assertSame([$otherItem->getId()],array_column($own['items'],'id'));
    }

}
