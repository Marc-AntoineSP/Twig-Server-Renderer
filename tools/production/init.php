<?php
$root=dirname(__DIR__,2);$name=$argv[1]??'production';if(!preg_match('/^[a-z][a-z0-9-]{0,40}$/D',$name))throw new RuntimeException('Use a local environment name.');$directory=$root.'/var/'.$name;
if(is_dir($directory)){if(is_file($directory.'/compose.env')){echo "Existing local production configuration preserved.\n";exit(0);}throw new RuntimeException('Refusing an unknown existing directory.');}
mkdir($directory.'/secrets',0700,true);chmod($directory,0700);$password=bin2hex(random_bytes(32));
$values=['app_secret'=>bin2hex(random_bytes(32)),'database_password'=>$password,'database_url'=>'postgresql://styleguide:'.$password.'@database:5432/styleguide?serverVersion=17&charset=utf8','render_token'=>bin2hex(random_bytes(32)),'build_token'=>bin2hex(random_bytes(32))];
foreach($values as $key=>$value){file_put_contents($directory.'/secrets/'.$key,$value."\n");chmod($directory.'/secrets/'.$key,0444);}
$env='STUDIO_PROJECT=styleguide-'.$name.PHP_EOL.'STUDIO_RELEASE=local-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4)).PHP_EOL.'STUDIO_SECRET_DIR=./var/'.$name.'/secrets'.PHP_EOL.'STUDIO_PRODUCTION_PORT=8096'.PHP_EOL;
file_put_contents($directory.'/compose.env',$env);chmod($directory.'/compose.env',0600);echo "Random local production secrets/configuration generated in private var/$name. No account or default password created.\n";
