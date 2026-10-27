<?php
// Test driver through the Docker PHP tool image; never included in production.
$root=dirname(__DIR__,2);$directory=$root.'/var/production-test';if(!is_dir($directory))mkdir($directory,0700,true);$path=$directory.'/accounts.json';
if(is_file($path))throw new RuntimeException('Existing production test credentials preserved; review their accounts before replacing.');
$accounts=[];foreach(['reader','editor','admin'] as $role){$account=['email'=>'production-'.bin2hex(random_bytes(8)).'@example.test','password'=>bin2hex(random_bytes(24))];$accounts[$role]=$account;file_put_contents($directory.'/'.$role.'.stdin',$account['password'].PHP_EOL.$account['password'].PHP_EOL);chmod($directory.'/'.$role.'.stdin',0600);file_put_contents($directory.'/'.$role.'.email',$account['email']);chmod($directory.'/'.$role.'.email',0600);}
file_put_contents($path,json_encode($accounts,JSON_THROW_ON_ERROR));chmod($path,0600);echo "Random production test credentials stored privately.\n";
