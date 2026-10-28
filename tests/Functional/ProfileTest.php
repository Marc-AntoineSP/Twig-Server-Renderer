<?php
namespace App\Tests\Functional;

use App\Entity\{ComposeToken,User};
use App\Mcp\Security\ComposeTokens;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use Symfony\Component\Uid\Ulid;

final class ProfileTest extends DatabaseTestCase
{
    public function testDetailsValidatePersistAndKeepCurrentSessionAndPermissions():void
    {
        $client=self::createClient();$owner=$this->account();$id=$owner->getId();$this->signIn($client,$owner);$client->request('GET','/profile');self::assertResponseIsSuccessful();self::assertSelectorTextContains('h1','My profile');self::assertSelectorExists('a[href="/profile"]');self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));self::assertFalse($client->getResponse()->headers->has('X-Debug-Token'));
        $client->request('POST','/profile/details',['displayName'=>'No CSRF','email'=>$owner->getEmail()]);self::assertResponseStatusCodeSame(403);
        $client->request('GET','/profile');$client->submitForm('Save account details',['displayName'=>'Studio editor','email'=>$owner->getEmail()]);self::assertResponseRedirects('/profile#account',303);$client->followRedirect();self::assertSelectorExists('input[value="Studio editor"]');
        $original=$owner->getEmail();$email='profile-'.strtolower((string)new Ulid()).'@example.test';
        $client->submitForm('Save account details',['displayName'=>'Keep attempted name','email'=>$email,'currentPassword'=>'wrong']);self::assertResponseStatusCodeSame(422);self::assertSelectorTextContains('[role="alert"]','current password');self::assertSelectorExists('input[value="Keep attempted name"]');self::assertSelectorNotExists('input[value="wrong"]');
        $em=self::getContainer()->get(EntityManagerInterface::class);self::assertSame($original,$em->find(User::class,$id)->getEmail());
        $client->request('GET','/profile');$client->submitForm('Save account details',['displayName'=>'Studio editor','email'=>strtoupper($email),'currentPassword'=>'Test password 2026!']);self::assertResponseRedirects('/profile#account',303);$client->followRedirect();self::assertSelectorTextContains('nav',$email);
        $user=self::getContainer()->get(EntityManagerInterface::class)->find(User::class,$id);self::assertSame($email,$user->getEmail());self::assertSame(['ROLE_EDITOR','ROLE_READER'],$user->getRoles());self::assertTrue($user->isEnabled());
        $client->request('GET','/');self::assertResponseIsSuccessful();
        $client->request('GET','/profile');$other=$this->account();$client->submitForm('Save account details',['email'=>$other->getEmail(),'currentPassword'=>'Test password 2026!']);self::assertResponseStatusCodeSame(422);self::assertSelectorTextContains('[role="alert"]','already in use');
    }

    public function testPasswordChangeRevokesCredentialsAndKeepsCurrentBrowserSignedIn():void
    {
        $client=self::createClient();$owner=$this->account();$id=$owner->getId();[, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Before password change',ComposeTokens::SCOPES);$this->signIn($client,$owner);
        $oldSessionUser=clone $owner;
        $em=self::getContainer()->get(EntityManagerInterface::class);$oauth=new \League\Bundle\OAuth2ServerBundle\Model\Client('Password fixture',bin2hex(random_bytes(16)),null);self::getContainer()->get(ClientManagerInterface::class)->save($oauth);
        $foreign=$this->account();$foreignId=bin2hex(random_bytes(40));$foreignAccess=new \League\Bundle\OAuth2ServerBundle\Model\AccessToken($foreignId,new \DateTimeImmutable('+1 hour'),$oauth,$foreign->getId(),[]);$em->persist($foreignAccess);
        $accessId=bin2hex(random_bytes(40));$refreshId=bin2hex(random_bytes(40));$codeId=bin2hex(random_bytes(40));
        $access=new \League\Bundle\OAuth2ServerBundle\Model\AccessToken($accessId,new \DateTimeImmutable('+1 hour'),$oauth,$id,[]);$refresh=new \League\Bundle\OAuth2ServerBundle\Model\RefreshToken($refreshId,new \DateTimeImmutable('+1 day'),$access);$code=new \League\Bundle\OAuth2ServerBundle\Model\AuthorizationCode($codeId,new \DateTimeImmutable('+5 minutes'),$oauth,$id,[]);$em->persist($access);$em->persist($refresh);$em->persist($code);$em->flush();
        $client->request('GET','/profile');$client->submitForm('Update password',['currentPassword'=>'Test password 2026!','newPassword'=>'A changed password 2026!','confirmation'=>'Different password 2026!']);self::assertResponseStatusCodeSame(422);
        $client->request('GET','/profile');$client->submitForm('Update password',['currentPassword'=>'Test password 2026!','newPassword'=>'A changed password 2026!','confirmation'=>'A changed password 2026!']);self::assertResponseRedirects('/profile#password',303);$client->followRedirect();self::assertResponseIsSuccessful();self::assertSelectorTextContains('[data-flash]','Password updated');
        $em=self::getContainer()->get(EntityManagerInterface::class);$changed=$em->find(User::class,$id);self::assertTrue(self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->isPasswordValid($changed,'A changed password 2026!'));self::assertNull(self::getContainer()->get(ComposeTokens::class)->verify($plain));self::assertStringNotContainsString('A changed password 2026!',$client->getResponse()->getContent());
        $client->request('GET','/');self::assertResponseIsSuccessful();
        $db=self::getContainer()->get(\Doctrine\DBAL\Connection::class);foreach(['oauth2_access_token'=>$accessId,'oauth2_refresh_token'=>$refreshId,'oauth2_authorization_code'=>$codeId] as $table=>$identifier)self::assertSame(1,(int)$db->fetchOne('SELECT CASE WHEN revoked THEN 1 ELSE 0 END FROM '.$table.' WHERE identifier = ?',[$identifier]));
        self::assertSame(0,(int)$db->fetchOne('SELECT CASE WHEN revoked THEN 1 ELSE 0 END FROM oauth2_access_token WHERE identifier = ?',[$foreignId]));
        $this->signIn($client,$oldSessionUser);$client->request('GET','/profile');self::assertResponseRedirects('/login');
    }

    public function testPersonalCredentialLifecycleScopesCsrfAndCrossAccountIsolation():void
    {
        $client=self::createClient();$owner=$this->account();$id=$owner->getId();$this->signIn($client,$owner);$client->request('GET','/profile');
        $form=$client->getCrawler()->selectButton('Create MCP credential')->form(['label'=>'Browser Codex','patterns'=>'profile-test/*','days'=>'3']);$values=$form->getPhpValues();$values['scopes']=['compose:read'];$client->request('POST','/profile/mcp/tokens',$values);self::assertResponseIsSuccessful();self::assertFalse($client->getResponse()->headers->has('X-Debug-Token'));$plain=$client->getCrawler()->filter('#mcp-bearer')->attr('value');self::assertMatchesRegularExpression('/^sgc_[a-f0-9]{64}$/D',$plain);
        $identity=self::getContainer()->get(ComposeTokens::class)->verify($plain);self::assertNotNull($identity);self::assertSame(['compose:read'],$identity->scopes);self::assertSame(['profile-test/*'],$identity->patterns);
        $record=self::getContainer()->get(EntityManagerInterface::class)->getRepository(ComposeToken::class)->findOneBy(['owner'=>$id,'label'=>'Browser Codex']);$tokenId=$record->getId();
        $client->request('GET','/profile/mcp/tokens');self::assertResponseRedirects('/profile#mcp',303);$client->followRedirect();self::assertSelectorExists('#mcp-bearer[value=""]');self::assertStringNotContainsString($plain,$client->getResponse()->getContent());
        $client->request('GET','/profile/mcp/codex.toml');self::assertResponseIsSuccessful();self::assertStringContainsString('bearer_token_env_var',$client->getResponse()->getContent());self::assertStringNotContainsString($plain,$client->getResponse()->getContent());
        $other=$this->account();$this->signIn($client,$other);$client->request('GET','/profile');self::assertStringNotContainsString('Browser Codex',$client->getResponse()->getContent());$csrf=$client->getCrawler()->filter('form[action="/profile/mcp/tokens"] input[name="_token"]')->attr('value');$client->request('POST','/profile/mcp/tokens/'.$tokenId.'/revoke',['_token'=>$csrf]);self::assertResponseStatusCodeSame(404);
        $em=self::getContainer()->get(EntityManagerInterface::class);$this->signIn($client,$em->find(User::class,$id));$client->request('GET','/profile');$client->submitForm('Revoke Browser Codex');self::assertResponseRedirects('/profile#mcp',303);self::assertNull(self::getContainer()->get(ComposeTokens::class)->verify($plain));
    }

    public function testReaderAndAnonymousRestrictionsAndAdminOAuthRegistration():void
    {
        $client=self::createClient();$client->request('GET','/profile');self::assertResponseRedirects('/login');$reader=$this->account('ROLE_READER');$this->signIn($client,$reader);$client->request('GET','/profile');self::assertResponseIsSuccessful();self::assertSelectorNotExists('form[action="/profile/mcp/tokens"]');$client->request('POST','/profile/mcp/tokens');self::assertResponseStatusCodeSame(403);$client->request('POST','/profile/mcp/oauth');self::assertResponseStatusCodeSame(403);
        $admin=$this->account('ROLE_ADMIN');$this->signIn($client,$admin);$client->request('GET','/profile');$client->submitForm('Create OAuth client',['label'=>'Profile ChatGPT','redirect'=>'https://evil.example.test/callback']);self::assertResponseStatusCodeSame(422);
        $client->request('GET','/profile');$client->submitForm('Create OAuth client',['label'=>'Profile ChatGPT','redirect'=>'https://chatgpt.com/connector_platform_oauth_redirect']);self::assertResponseIsSuccessful();$id=$client->getCrawler()->filter('#oauth-client-id')->attr('value');$plain=$client->getCrawler()->filter('#oauth-client-secret')->attr('value');self::assertNotEmpty($plain);$record=self::getContainer()->get(ClientManagerInterface::class)->find($id);self::assertNotSame($plain,$record->getSecret());self::assertTrue(password_verify($plain,$record->getSecret()));self::assertFalse($record->isPlainTextPkceAllowed());
        $client->request('GET','/profile');self::assertStringNotContainsString($plain,$client->getResponse()->getContent());$client->submitForm('Disable Profile ChatGPT');self::assertResponseRedirects('/profile#chatgpt',303);self::assertFalse(self::getContainer()->get(ClientManagerInterface::class)->find($id)->isActive());
    }
    public function testProfileRevisionAndReaderFieldsCannotOverwriteOrEscalate():void
    {
        $client=self::createClient();$owner=$this->account('ROLE_READER');$id=$owner->getId();$this->signIn($client,$owner);$client->request('GET','/profile');$form=$client->getCrawler()->selectButton('Save account details')->form();$old=$form->getPhpValues();
        $client->submitForm('Save account details',['displayName'=>'Latest profile']);self::assertResponseRedirects();$old['displayName']='Stale profile';$client->request('POST','/profile/details',$old);self::assertResponseStatusCodeSame(412);
        $client->request('GET','/profile');$values=$client->getCrawler()->selectButton('Save account details')->form()->getPhpValues();$values['displayName']='<script>literal profile</script>';$values['roles']=['ROLE_ADMIN'];$values['enabled']='0';$client->request('POST','/profile/details',$values);self::assertResponseRedirects();$client->followRedirect();self::assertStringNotContainsString('<script>literal profile</script>',$client->getResponse()->getContent());
        $user=self::getContainer()->get(EntityManagerInterface::class)->find(User::class,$id);self::assertSame(['ROLE_READER'],$user->getRoles());self::assertTrue($user->isEnabled());
    }
    public function testSensitivePasswordAttemptsAreThrottled():void
    {
        $client=self::createClient();$owner=$this->account();$this->signIn($client,$owner);
        for($attempt=0;$attempt<6;$attempt++){$client->request('GET','/profile');$client->submitForm('Update password',['currentPassword'=>'wrong','newPassword'=>'Valid new password 2026!','confirmation'=>'Valid new password 2026!']);self::assertResponseStatusCodeSame($attempt<5?422:429);}
        self::assertSelectorTextContains('[role="alert"]','Too many password attempts');
    }

}
