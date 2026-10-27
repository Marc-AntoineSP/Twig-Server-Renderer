<?php
require '/consumer/vendor/autoload.php';
use Symfony\Component\Process\Process;
$state=json_decode(file_get_contents('/workspace/var/consumer-state.json'),true,512,JSON_THROW_ON_ERROR);$phase=$argv[1]??'install';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function command(array $args,bool $success=true,array $env=[]):array{
    $process=new Process(['php','bin/console',...$args,'--no-interaction'],'/consumer',$env);$process->setTimeout(90);$process->run();$out=$process->getOutput();$error=$process->getErrorOutput();
    check($process->isSuccessful()===$success,implode(' ',$args).' unexpected exit '.$process->getExitCode().': '.$out.' '.$error);
    if($success&&str_starts_with(trim($out),'{'))return json_decode(substr($out,0,strrpos($out,'}')+1),true,512,JSON_THROW_ON_ERROR);
    return ['output'=>$out];
}
function snapshot():array{
    $hashes=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/consumer',FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){$path=substr($file->getPathname(),10);if(preg_match('~^(?:vendor|node_modules|var|public/assets|\.styleguide/staging)/|^\.styleguide/install.lock$~',$path)||!$file->isFile())continue;$hashes[$path]=hash_file('sha256',$file->getPathname());}ksort($hashes);return $hashes;
}
function stageJournals():array{return glob('/consumer/.styleguide/staging/*/journal.json')?:[];}
if($phase==='install'){
    command(['styleguide:registry:add','local',getenv('STUDIO_REGISTRY_URL')?:'http://studio/api/v1/registry','--allow-http']);
    $zip=new ZipArchive();check($zip->open('/workspace/var/consumer-artifacts/alert.zip')===true,'Cannot read fixture archive.');$bytes=$zip->getFromName($state['entry']);$zip->close();$path='/consumer/'.$state['entry'];mkdir(dirname($path),0775,true);file_put_contents($path,$bytes);$before=snapshot();
    command(['styleguide:require',$state['alert'].'@1.0.0','--registry=local'],false);check(snapshot()===$before,'Unowned collision partially wrote files.');unlink($path);
    $zip=new ZipArchive();$zip->open('/workspace/var/consumer-artifacts/alert.zip');$manifest=json_decode($zip->getFromName('manifest.json'));$sources=new stdClass();foreach($manifest->files as $f)$sources->{$f->path}=$zip->getFromName($f->path);$zip->close();
    $js=$manifest->controllers[0]->path;$originalJs=$sources->{$js};$sources->{$js}='import "node:fs"; export default class {}';
    foreach($manifest->files as $f)if($f->path===$js){$f->sha256=hash('sha256',$sources->{$js});$f->bytes=strlen($sources->{$js});}
    file_put_contents('/workspace/var/consumer-artifacts/invalid.json',json_encode(['schemaVersion'=>1,'manifest'=>$manifest,'sources'=>$sources],JSON_THROW_ON_ERROR));$before=snapshot();
    $rejected=command(['styleguide:install','/workspace/var/consumer-artifacts/invalid.json'],false);check(str_contains($rejected['output'],'compiler import')&&snapshot()===$before,'Local-file JavaScript policy bypass or partial write.');
    $sources->{$js}=$originalJs;$cssPath='assets/styles/catalogue/'.$manifest->name.'/imported.css';$sources->{$cssPath}='@\\70 lugin "./payload.js";';$manifest->files=[];foreach($sources as $file=>$content)$manifest->files[]=(object)['path'=>$file,'sha256'=>hash('sha256',$content),'bytes'=>strlen($content)];
    file_put_contents('/workspace/var/consumer-artifacts/invalid-css.json',json_encode(['schemaVersion'=>1,'manifest'=>$manifest,'sources'=>$sources],JSON_THROW_ON_ERROR));$before=snapshot();$rejected=command(['styleguide:install','/workspace/var/consumer-artifacts/invalid-css.json'],false);check(str_contains($rejected['output'],'CSS directives')&&snapshot()===$before,'Escaped executable CSS directive bypass or partial write.');
    $before=snapshot();$plan=command(['styleguide:require',$state['alert'].'@1.0.0','--registry=local','--dry-run']);check($plan['dryRun']&&$plan['plan']['browserCodeReview'],'No concrete code review.');check(snapshot()===$before,'Dry-run modified consumer files.');
    command(['styleguide:require',$state['alert'].'@1.0.0','--registry=local']);$before=snapshot();$again=command(['styleguide:require',$state['alert'].'@1.0.0','--registry=local']);check($again['changed']===0&&snapshot()===$before,'Reinstall not idempotent.');
    command(['styleguide:require',$state['layout'].'@1.0.0','--registry=local']);command(['styleguide:theme:install',$state['theme'].'@1.0.0','--registry=local']);
    $original=file_get_contents($path);file_put_contents($path,$original."\n{# local consumer change #}\n");$before=snapshot();command(['styleguide:require',$state['layout'].'@1.0.0','--registry=local'],false);check(snapshot()===$before,'Modified-file conflict partially wrote.');file_put_contents($path,$original);
    $before=snapshot();command(['styleguide:update',$state['alert'].'@1.1.0'],false);check(snapshot()===$before,'Pinned update partially wrote.');command(['styleguide:remove',$state['alert']],false);check(snapshot()===$before,'Dependent removal partially wrote.');
    // Kill a real CLI after its first journalled mutation, then recover with a fresh CLI.
    $process=new Process(['php','bin/console','styleguide:remove',$state['theme'],'--no-interaction'],'/consumer',['APP_ENV'=>'test','STYLEGUIDE_TEST_PAUSE_AFTER'=>'1']);$process->setTimeout(null);$process->start();$paused=false;
    for($i=0;$i<400;$i++){foreach(stageJournals() as $journal){$data=json_decode(file_get_contents($journal),true);if(($data['applied']??0)===1)$paused=true;}if($paused||!$process->isRunning())break;usleep(100000);}
    check($paused,'Crash test did not reach a mutation: '.$process->getOutput().$process->getErrorOutput());$process->stop(0,9);check(stageJournals()!==[],'Interrupted journal missing.');$doctor=command(['styleguide:doctor','--recover']);check(count($doctor['recovered'])===1&&$doctor['healthy'],'Journal not recovered.');check(snapshot()===$before,'Crash rollback did not restore exact preinstall file hashes.');
    command(['asset-map:compile','--env=prod','--no-debug']);command(['cache:clear','--env=prod','--no-debug']);
    echo "PASS: real registry install, exact closure, dry-run, idempotence, collisions, modified files, pinned dependents and SIGKILL recovery; production assets compiled.\n";
}elseif($phase==='update'){
    command(['styleguide:remove',$state['layout']]);command(['styleguide:update',$state['alert'].'@1.1.0']);check(str_contains(file_get_contents('/consumer/'.$state['entry']),'data-updated="yes"'),'New version not installed.');
    command(['styleguide:remove',$state['alert']]);check(!is_file('/consumer/'.$state['entry']),'Removed owned template still exists.');check(command(['styleguide:doctor'])['healthy'],'Doctor failed after removal.');
    command(['styleguide:install','/workspace/var/consumer-artifacts/collection.zip']);$before=snapshot();$again=command(['styleguide:install','/workspace/var/consumer-artifacts/collection.zip']);check($again['changed']===0&&snapshot()===$before,'Collection install not idempotent.');
    command(['asset-map:compile','--env=prod','--no-debug']);command(['cache:clear','--env=prod','--no-debug']);echo "PASS: dependency-aware removal/update, owned-file deletion and offline resolved-collection installation.\n";
}else throw new RuntimeException('Unknown phase.');
