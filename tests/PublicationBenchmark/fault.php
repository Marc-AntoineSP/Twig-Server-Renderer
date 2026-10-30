<?php
// Invoked only by the owned runner while its exact compiler service is stopped/restarted.
require dirname(__DIR__,2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__,2).'/.env');
$context=new App\Tests\PublicationBenchmark\Context();$fixture=$context->fixtures()['normal'];$mode=$argv[1]??'';
if(!in_array($mode,['unavailable','retry'],true))throw new RuntimeException('Use unavailable or retry.');
$client=$context->client();$version='8.0.0';
$response=$client->json('POST','/api/v1/items/'.$fixture['id'].'/releases',['version'=>$version,'expectedRevision'=>$fixture['revision']]);
if($mode==='unavailable'){
    if($response['status']!==503||$context->release($fixture,$version))throw new RuntimeException('Worker failure must not expose a release.');
    $result=[...App\Tests\PublicationBenchmark\Client::metrics($response),'code'=>$response['data']->code,'message'=>$response['data']->message,'visibleReleases'=>0];
}else{
    if($response['status']!==201)throw new RuntimeException('Same-version retry after worker recovery failed.');
    $result=[...App\Tests\PublicationBenchmark\Client::metrics($response),...$context->verifyRelease($fixture,$version)];
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
