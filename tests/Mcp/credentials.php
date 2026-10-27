<?php
/** Dedicated local verification actor; no user credentials are reused. Never web-routed. */
$root=dirname(__DIR__,2);require $root.'/vendor/autoload.php';(new Symfony\Component\Dotenv\Dotenv())->bootEnv($root.'/.env');
$kernel=new App\Kernel($_SERVER['APP_ENV']??'dev',false);$kernel->boot();$em=$kernel->getContainer()->get('doctrine')->getManager();$path=$root.'/var/mcp-verification/codex-credential.json';
if(($argv[1]??'')==='cleanup'){
    if(is_file($path)){$v=json_decode(file_get_contents($path),true);$u=$em->find(App\Entity\User::class,$v['userId']);if($u&&str_starts_with($u->getEmail(),'mcp-codex-')){$u->disable();$em->find(App\Entity\ComposeToken::class,$v['tokenId'])?->revoke();$em->flush();}unlink($path);}echo "Dedicated Codex credential revoked and file removed.\n";return;
}
if(is_file($path))throw new RuntimeException('Existing verification credentials are preserved; cleanup first.');
$u=new App\Entity\User('mcp-codex-'.bin2hex(random_bytes(8)).'@example.test','ROLE_EDITOR');$u->setPassword(password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT));$em->persist($u);$em->flush();
[$t,$p]=(new App\Mcp\Security\ComposeTokens($em))->issue($u,'Codex client verification',App\Mcp\Security\ComposeTokens::SCOPES,['mcp-codex/*']);
if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,json_encode(['userId'=>$u->getId(),'tokenId'=>$t->getId(),'token'=>$p],JSON_THROW_ON_ERROR));chmod($path,0600);echo "Dedicated Codex credential stored privately.\n";
