<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__,2).'/.env');
$context = new App\Tests\PublicationBenchmark\Context();
if (is_file($context::RECEIPT)) throw new RuntimeException('This run already has publication fixtures; refusing to reseed.');
$client = $context->client(); $json = new Styleguide\Contracts\CanonicalJson();$profiles=[];
foreach (['normal'=>1,'examples'=>20,'large'=>20,'invalid'=>3,'over-limit'=>21] as $profile=>$examples) {
    $name='publish-bench/'.$profile.'-'.substr($context->owner['runId'],-8);
    $created=$client->json('POST','/api/v1/items',['name'=>$name,'kind'=>'component','title'=>'Publication benchmark '.$profile]);
    if($created['status']!==201)throw new RuntimeException('Could not create owned publication fixture: '.$profile);
    $draft=$created['data'];$manifest=$draft->manifestData;$sources=$draft->sourceMap;
    $entry=$manifest->entry;
    $identifier='catalogue--'.str_replace('/','--',$name);$controller='assets/controllers/catalogue/'.$name.'_controller.js';
    $sources->$controller="import {Controller} from '@hotwired/stimulus'; export default class extends Controller {connect(){this.element.dataset.ready='true';}}\n";
    $manifest->controllers=[(object)['identifier'=>$identifier,'path'=>$controller]];
    $sources->$entry='<section data-controller="'.$identifier.'" class="rounded-box bg-base-100 p-6"><h2 class="text-xl font-bold">{{ message }}</h2><p class="text-sm">Bounded publication fixture.</p></section>';
    $list=[];for($i=0;$i<$examples;$i++)$list[]=(object)['name'=>'Example '.($i+1),'props'=>(object)['message'=>'Example '.($i+1).' '.str_repeat('content ',12)]];
    if($profile==='large'){
        // Two sizeable valid files beneath the existing 1MiB/file and 2MiB/closure caps.
        $sources->$entry='{#'.str_repeat(' bounded source padding ',32000).'#}'.$sources->$entry;
        $sources->{'templates/catalogue/'.$name.'/supplement.html.twig'}='{#'.str_repeat(' supplemental fixture source ',22000).'#}';
    }
    if($profile==='invalid'){
        $list[$examples-1]->props->message='last-invalid';
        $sources->$entry.='{% if message == "last-invalid" %}{{ 1 / 0 }}{% endif %}';
    }
    $sources->{'meta/examples.json'}=json_encode($list,JSON_THROW_ON_ERROR);
    $saved=$client->json('PUT','/api/v1/items/'.$draft->id.'/draft',['title'=>$manifest->title,'manifestData'=>$manifest,'sourceMap'=>$sources],['If-Match: "1"']);
    if($saved['status']!==200)throw new RuntimeException('Could not save owned publication fixture: '.$profile.' HTTP '.$saved['status']);
    $data=$saved['data'];$bytes=0;foreach($data->sourceMap as $source)$bytes+=strlen($source);
    $profiles[$profile]=['id'=>$data->id,'name'=>$name,'revision'=>$data->revision,'examples'=>$examples,'renderBuildPairs'=>1+$examples,'sourceBytes'=>$bytes,'files'=>count((array)$data->sourceMap),'sourceHash'=>hash('sha256',$json->encode($data->sourceMap))];
}
file_put_contents($context::RECEIPT,json_encode(['seed'=>'publication-v1','runId'=>$context->owner['runId'],'profiles'=>$profiles],JSON_THROW_ON_ERROR));chmod($context::RECEIPT,0600);
echo json_encode(['seed'=>'publication-v1','profiles'=>$profiles],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
