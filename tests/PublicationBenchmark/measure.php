<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__,2).'/.env');
$context=new App\Tests\PublicationBenchmark\Context();$fixtures=$context->fixtures();$client=$context->client();$results=[];
foreach(['normal','examples','large'] as $name){
    $fixture=$fixtures[$name];$samples=[];
    for($iteration=0;$iteration<=5;$iteration++){
        $version='1.0.'.$iteration;
        if($context->release($fixture,$version))throw new RuntimeException('Measurement version already exists; use a fresh owned run.');
        $response=$client->json('POST','/api/v1/items/'.$fixture['id'].'/releases',['version'=>$version,'expectedRevision'=>$fixture['revision']]);
        if($response['status']!==201)throw new RuntimeException('Publication failed for '.$name.' HTTP '.$response['status'].' code '.($response['data']->code??'unavailable'));
        $verified=$context->verifyRelease($fixture,$version);
        if($iteration)$samples[]=[...App\Tests\PublicationBenchmark\Client::metrics($response),...$verified];
        fwrite(STDERR,$name.' repetition '.$iteration.': '.$response['elapsedMs']." ms\n");
    }
    $results[$name]=['fixture'=>$fixture,'samples'=>$samples];
}
$failures=[];
foreach(['invalid','over-limit'] as $name){
    $fixture=$fixtures[$name];$version='2.0.0';$before=count(glob('/app/var/catalogue/releases/*.zip')?:[]);
    $response=$client->json('POST','/api/v1/items/'.$fixture['id'].'/releases',['version'=>$version,'expectedRevision'=>$fixture['revision']]);
    if($response['status']!==422||$context->release($fixture,$version))throw new RuntimeException('Expected validation failure without a release.');
    $failures[$name]=[...App\Tests\PublicationBenchmark\Client::metrics($response),'code'=>$response['data']->code,'message'=>$response['data']->message,'visibleReleases'=>0,'newArchives'=>count(glob('/app/var/catalogue/releases/*.zip')?:[])-$before];
}
// A lost response is an unknown outcome: observe the committed version independently,
// then verify the existing conflict policy and complete archive on an exact retry.
$fixture=$fixtures['examples'];$version='9.0.0';
$response=$client->json('POST','/api/v1/items/'.$fixture['id'].'/releases',['version'=>$version,'expectedRevision'=>$fixture['revision']],timeoutMs:150);
if($response['curlError']!==CURLE_OPERATION_TIMEDOUT)throw new RuntimeException('Disconnect probe did not interrupt the response.');
$started=microtime(true);$deadline=$started+90;
do{$release=$context->release($fixture,$version);if($release)break;usleep(100000);}while(microtime(true)<$deadline);
$verified=$context->verifyRelease($fixture,$version);
$retry=$client->json('POST','/api/v1/items/'.$fixture['id'].'/releases',['version'=>$version,'expectedRevision'=>$fixture['revision']]);
if($retry['status']!==409)throw new RuntimeException('Exact disconnected retry must preserve the existing immutable version.');
$disconnected=['client'=>App\Tests\PublicationBenchmark\Client::metrics($response),'observedCommitAfterDisconnectMs'=>round((microtime(true)-$started)*1000,3),'retry'=>App\Tests\PublicationBenchmark\Client::metrics($retry),'result'=>$verified,'releaseCount'=>(int)$context->em->getConnection()->fetchOne('SELECT COUNT(*) FROM catalogue_release WHERE item_id=? AND version=?',[$fixture['id'],$version])];
echo json_encode(['seed'=>'publication-v1','conditions'=>'Real HTTP, isolated FrankenPHP dev/debug=false, real authenticated editor, no injected latency or worker stubs, one excluded first publication +5 measured versions per profile, no other preview/build load; each build invokes the fixed real toolchain','runtime'=>['php'=>PHP_VERSION,'symfony'=>Symfony\Component\HttpKernel\Kernel::VERSION,'postgres'=>$context->em->getConnection()->fetchOne('SHOW server_version'),'logicalCpus'=>trim(shell_exec('getconf _NPROCESSORS_ONLN')),'memoryLimit'=>ini_get('memory_limit')],'results'=>$results,'validationFailures'=>$failures,'disconnect'=>$disconnected],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
