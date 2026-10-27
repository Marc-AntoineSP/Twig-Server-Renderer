<?php
/** Official SDK HTTP client; reusable by the Docker command or a private token file. */
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;

return static function(string $url,string $plain):array {
    $client=Client::builder()->setClientInfo('styleguide-compose-smoke','1.0')->setInitTimeout(15)->setRequestTimeout(45)->setMaxRetries(0)->build();
    $client->connect(new HttpTransport($url,['Authorization'=>'Bearer '.$plain]));
    $calls=[];
    $call=static function(string $name,array $arguments=[])use($client,&$calls):array{
        try{$result=$client->callTool('compose_'.$name,$arguments);}catch(\Throwable $error){throw new RuntimeException('MCP '.$name.' transport/protocol failed: '.$error->getMessage(),0,$error);}if($result->isError)throw new RuntimeException('MCP '.$name.' failed: '.($result->content[0]->text??'Unknown error.'));
        $calls[]=$name;return (array)$result->structuredContent;
    };
    try {
        $tools=$client->listTools();if(count($tools->tools)!==26)throw new RuntimeException('Unexpected tool allowlist.');
        $call('capabilities');$catalogue=$call('search_catalogue',['query'=>'studio-showcase','limit'=>50]);$reference=null;
        foreach($catalogue['items'] as $item)if($item['kind']==='component'&&count($item['releases'])){$reference=$call('get_reference',['name'=>$item['name'],'version'=>$item['releases'][0]['version']])['reference'];break;}
        if(!$reference)throw new RuntimeException('Seed the existing studio showcase before running this smoke.');
        $created=$call('create_draft',['name'=>'mcp-smoke/layout-'.bin2hex(random_bytes(5)),'kind'=>'layout','title'=>'MCP smoke layout','operationId'=>'create-'.bin2hex(random_bytes(8))]);$id=$created['compositionId'];$root=$created['document']['root']['id'];
        $batch=['compositionId'=>$id,'expectedRevision'=>1,'operationId'=>'batch-'.bin2hex(random_bytes(8)),'commands'=>[['type'=>'add_container','parentId'=>$root,'position'=>0,'alias'=>'responsive','classes'=>['grid','gap-4','md:grid-cols-3']],['type'=>'add_reference','parentId'=>'$responsive','position'=>0,'name'=>$reference['name'],'version'=>$reference['version'],'alias'=>'card'],['type'=>'add_text','parentId'=>$root,'position'=>1,'value'=>'{{ literal }} FAQ example','alias'=>'faq']]];
        $edited=$call('apply_batch',$batch);$retry=$call('apply_batch',$batch);if($edited['revision']!==2||$retry['revision']!==2||!$retry['replayed'])throw new RuntimeException('Retry was not exactly once.');
        $node=$edited['aliases']['faq'];$revision=2;
        foreach(['indent_node','outdent_node','move_up','move_down'] as $action){$r=$call($action,['compositionId'=>$id,'expectedRevision'=>$revision,'operationId'=>$action.'-'.bin2hex(random_bytes(6)),'nodeId'=>$node]);$revision=$r['revision'];}
        $duplicated=$call('duplicate_node',['compositionId'=>$id,'expectedRevision'=>$revision,'operationId'=>'duplicate-'.bin2hex(random_bytes(6)),'nodeId'=>$node]);$revision=$duplicated['revision'];
        $removed=$call('remove_node',['compositionId'=>$id,'expectedRevision'=>$revision,'operationId'=>'remove-'.bin2hex(random_bytes(6)),'nodeId'=>$duplicated['selectedNodeId']]);$revision=$removed['revision'];
        $call('get_node',['compositionId'=>$id,'nodeId'=>$edited['aliases']['card']]);$call('validate',['compositionId'=>$id,'revision'=>$revision]);
        $themes=$call('list_preview_themes',['limit'=>1]);$themeId=$themes['themes'][0]['id']??null;
        $previewInput=['compositionId'=>$id,'revision'=>$revision,'operationId'=>'preview-'.bin2hex(random_bytes(6)),'width'=>768];if($themeId)$previewInput['themeReleaseId']=$themeId;
        $preview=$call('render_preview',$previewInput);
        $deadline=microtime(true)+60;
        do{$result=$call('get_preview',['jobId'=>$preview['jobId'],'includeHtml'=>true]);if(in_array($result['status'],['failed','cancelled','expired']))throw new RuntimeException('Real preview failed: '.json_encode($result['errors']));if($result['status']==='succeeded')break;usleep(200000);}while(microtime(true)<$deadline);
        if($result['status']!=='succeeded'||$result['previewRevision']!==$revision||!str_contains($result['html']??'','Content-Security-Policy')||str_contains($result['html']??'','allow-same-origin'))throw new RuntimeException('Exact isolated preview not verified: '.json_encode(['status'=>$result['status'],'expectedRevision'=>$revision,'previewRevision'=>$result['previewRevision']??null,'htmlBytes'=>strlen($result['html']??''),'hasCsp'=>str_contains($result['html']??'','Content-Security-Policy'),'containsSameOrigin'=>str_contains($result['html']??'','allow-same-origin')]));
        $read=$call('get',['compositionId'=>$id]);if($read['revision']!==$revision)throw new RuntimeException('Saved revision differs.');
        $history=$call('get_history',['compositionId'=>$id]);$restored=$call('restore_revision',['compositionId'=>$id,'expectedRevision'=>$revision,'restoreRevision'=>1,'operationId'=>'restore-'.bin2hex(random_bytes(6))]);if($restored['revision']!==$revision+1||$restored['document']['root']['children']!==[])throw new RuntimeException('History restoration failed.');
        return ['client'=>'official mcp/sdk','protocolVersion'=>$client->getProtocolVersion()?->value,'toolCount'=>count($tools->tools),'compositionId'=>$id,'composerUrl'=>$read['composerUrl'],'previewRevision'=>$revision,'restoredRevision'=>$restored['revision'],'previewJobId'=>$preview['jobId'],'themeReleaseId'=>$themeId,'previewVerified'=>true,'visualInspectionAvailable'=>false,'calls'=>array_count_values($calls)];
    }finally{$client->disconnect();}
};
