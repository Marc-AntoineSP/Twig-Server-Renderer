<?php
namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\{AccessTokenManagerInterface,ClientManagerInterface};
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\{Grant,RedirectUri,Scope};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Real authorization-code/PKCE exchange against the maintained OAuth server. */
final class ComposeOAuthTest extends DatabaseTestCase
{
    private const RESOURCE='http://127.0.0.1:8095/mcp/compose';
    private const REDIRECT='https://client.example.test/exact/callback';
    private function registration():string
    {
        $id=bin2hex(random_bytes(16));
        $client=new Client('Composition OAuth test',$id,null);
        $client->setRedirectUris(new RedirectUri(self::REDIRECT))->setGrants(new Grant('authorization_code'),new Grant('refresh_token'))->setScopes(new Scope('compose:read'),new Scope('compose:edit'),new Scope('compose:create'),new Scope('compose:preview'));
        self::getContainer()->get(ClientManagerInterface::class)->save($client);return $id;
    }
    private function authorization(string $id,array $changes=[]):array
    {
        return array_replace(['response_type'=>'code','client_id'=>$id,'redirect_uri'=>self::REDIRECT,'resource'=>self::RESOURCE,'scope'=>'compose:read compose:edit','state'=>'state-'.bin2hex(random_bytes(8)),'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',str_repeat('v',64),true)),'+/','-_'),'='),'code_challenge_method'=>'S256'],$changes);
    }
    private function exchange(KernelBrowser $browser,array $input):array
    {
        $browser->request('POST','/oauth2/compose/token',$input,server:['HTTP_ACCEPT'=>'application/json']);
        return json_decode($browser->getResponse()->getContent(),true,64,JSON_THROW_ON_ERROR);
    }
    private function authorize(KernelBrowser $browser,array $query):string
    {
        $browser->request('GET','/oauth2/compose/authorize?'.http_build_query($query));
        self::assertResponseRedirects();self::assertStringStartsWith('/oauth2/compose/approval/',$browser->getResponse()->headers->get('Location'));
        $browser->followRedirect();self::assertResponseIsSuccessful();self::assertSelectorTextContains('body','Composition OAuth test');
        $browser->submitForm('Allow composer access');self::assertResponseRedirects(null,303);
        $browser->followRedirect();self::assertResponseRedirects();
        $location=$browser->getResponse()->headers->get('Location');self::assertStringStartsWith(self::REDIRECT.'?',$location);
        parse_str(parse_url($location,PHP_URL_QUERY),$result);self::assertSame($query['state'],$result['state']);self::assertSame('http://127.0.0.1:8095',$result['iss']);
        return $result['code'];
    }
    private function mcp(KernelBrowser $browser,string $token):void
    {
        $browser->request('POST','/mcp/compose',server:['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream','HTTP_AUTHORIZATION'=>'Bearer '.$token],content:json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25','capabilities'=>(object)[],'clientInfo'=>['name'=>'oauth-tests','version'=>'1']]]));
    }
    public function testDiscoveryConsentPkceExchangeRefreshRevocationAndDisabledOwner():void
    {
        $browser=self::createClient();$owner=$this->account();$ownerId=$owner->getId();$id=$this->registration();$this->signIn($browser,$owner);
        $browser->request('GET','/.well-known/oauth-protected-resource/mcp/compose');self::assertResponseIsSuccessful();$metadata=json_decode($browser->getResponse()->getContent(),true);self::assertSame(self::RESOURCE,$metadata['resource']);self::assertContains('http://127.0.0.1:8095',$metadata['authorization_servers']);
        $browser->request('GET','/.well-known/oauth-authorization-server');self::assertResponseIsSuccessful();$metadata=json_decode($browser->getResponse()->getContent(),true);self::assertContains('S256',$metadata['code_challenge_methods_supported']);self::assertSame('http://127.0.0.1:8095/oauth2/compose/token',$metadata['token_endpoint']);
        $query=$this->authorization($id);$code=$this->authorize($browser,$query);
        $request=['grant_type'=>'authorization_code','client_id'=>$id,'redirect_uri'=>self::REDIRECT,'resource'=>self::RESOURCE,'code'=>$code,'code_verifier'=>str_repeat('x',64)];
        $bad=$this->exchange($browser,$request);self::assertResponseStatusCodeSame(400);self::assertSame('invalid_grant',$bad['error']);
        $request['code_verifier']=str_repeat('v',64);$tokens=$this->exchange($browser,$request);self::assertResponseIsSuccessful();self::assertSame('Bearer',$tokens['token_type']);self::assertSame(3600,$tokens['expires_in']);self::assertNotEmpty($tokens['refresh_token']);
        $claims=json_decode(base64_decode(strtr(explode('.',$tokens['access_token'])[1],'-_','+/')),true);self::assertSame($ownerId,$claims['sub']);
        $this->mcp($browser,$tokens['access_token']);self::assertResponseIsSuccessful();
        $replay=$this->exchange($browser,$request);self::assertResponseStatusCodeSame(400);self::assertSame('invalid_grant',$replay['error']);
        $refresh=['grant_type'=>'refresh_token','client_id'=>$id,'resource'=>self::RESOURCE,'refresh_token'=>$tokens['refresh_token']];$renewed=$this->exchange($browser,$refresh);self::assertResponseIsSuccessful();self::assertNotSame($tokens['access_token'],$renewed['access_token']);
        $this->mcp($browser,$renewed['access_token']);self::assertResponseIsSuccessful();
        $manager=self::getContainer()->get(AccessTokenManagerInterface::class);$claims=json_decode(base64_decode(strtr(explode('.',$renewed['access_token'])[1],'-_','+/')),true);$record=$manager->find($claims['jti']);$record->revoke();$manager->save($record);
        $this->mcp($browser,$renewed['access_token']);self::assertResponseStatusCodeSame(401);
        $again=$this->exchange($browser,['grant_type'=>'refresh_token','client_id'=>$id,'resource'=>self::RESOURCE,'refresh_token'=>$renewed['refresh_token']]);self::assertResponseIsSuccessful();
        $em=self::getContainer()->get(EntityManagerInterface::class);$em->find(User::class,$ownerId)->disable();$em->flush();$this->mcp($browser,$again['access_token']);self::assertResponseStatusCodeSame(401);
    }
    public function testExactRedirectResourcePkceAndConsentCsrf():void
    {
        $browser=self::createClient();$owner=$this->account();$id=$this->registration();$this->signIn($browser,$owner);
        foreach([
            ['redirect_uri'=>self::REDIRECT.'/extra'],['resource'=>'https://wrong.example.test/mcp/compose'],['resource'=>''],['code_challenge_method'=>'plain'],['code_challenge_method'=>'S256','code_challenge'=>''],
        ] as $changes){$browser->request('GET','/oauth2/compose/authorize?'.http_build_query($this->authorization($id,$changes)));self::assertResponseStatusCodeSame(isset($changes['redirect_uri'])?401:400);}
        $query=$this->authorization($id);$browser->request('GET','/oauth2/compose/authorize?'.http_build_query($query));self::assertResponseRedirects();$url=$browser->getResponse()->headers->get('Location');$browser->request('POST',$url,['decision'=>'approve','_token'=>'invalid']);self::assertResponseStatusCodeSame(403);
        $browser->request('GET',$url);$browser->submitForm('Deny connection');self::assertResponseStatusCodeSame(403);
        $browser->request('GET',$url);self::assertResponseStatusCodeSame(404);
        $tokens=$this->exchange($browser,['grant_type'=>'client_credentials','client_id'=>$id,'resource'=>self::RESOURCE]);self::assertResponseStatusCodeSame(400);self::assertSame('unsupported_grant_type',$tokens['error']);
    }
    public function testConfidentialClientRequiresSecretAndPkceAndClientDisableRevokesAccess():void
    {
        $browser=self::createClient();$owner=$this->account();$id=$this->registration();$this->signIn($browser,$owner);
        $manager=self::getContainer()->get(ClientManagerInterface::class);$client=$manager->find($id);$secret=bin2hex(random_bytes(32));$client->setSecret(password_hash($secret,PASSWORD_BCRYPT));$manager->save($client);
        $browser->request('GET','/oauth2/compose/authorize?'.http_build_query($this->authorization($id,['code_challenge'=>'','code_challenge_method'=>''])));self::assertResponseStatusCodeSame(400);
        $code=$this->authorize($browser,$this->authorization($id));$input=['grant_type'=>'authorization_code','client_id'=>$id,'redirect_uri'=>self::REDIRECT,'resource'=>self::RESOURCE,'code'=>$code,'code_verifier'=>str_repeat('v',64)];
        $bad=$this->exchange($browser,$input+['client_secret'=>'wrong']);self::assertResponseStatusCodeSame(401);self::assertSame('invalid_client',$bad['error']);
        $tokens=$this->exchange($browser,$input+['client_secret'=>$secret]);self::assertResponseIsSuccessful();$this->mcp($browser,$tokens['access_token']);self::assertResponseIsSuccessful();
        $manager=self::getContainer()->get(ClientManagerInterface::class);$client=$manager->find($id);$client->setActive(false);$manager->save($client);$this->mcp($browser,$tokens['access_token']);self::assertResponseStatusCodeSame(401);
    }

    public function testConsentCannotApproveDifferentScopesOrBeReused():void
    {
        $browser=self::createClient();$owner=$this->account();$id=$this->registration();$this->signIn($browser,$owner);
        $browser->request('GET','/oauth2/compose/authorize?'.http_build_query($this->authorization($id)));$browser->followRedirect();$browser->submitForm('Allow composer access');self::assertResponseRedirects(null,303);
        $location=$browser->getResponse()->headers->get('Location');parse_str(parse_url($location,PHP_URL_QUERY),$query);$query['scope']='compose:read compose:edit compose:create';$browser->request('GET','/oauth2/compose/authorize?'.http_build_query($query));self::assertResponseStatusCodeSame(403);$error=json_decode($browser->getResponse()->getContent(),true);self::assertSame('access_denied',$error['error']);
        // The rejected approval is consumed: the original query requires fresh consent.
        $browser->request('GET',$location);self::assertResponseRedirects();self::assertStringStartsWith('/oauth2/compose/approval/',$browser->getResponse()->headers->get('Location'));
        $browser->request('POST','/oauth2/compose/token?resource='.urlencode('https://wrong.example.test/mcp/compose'),['resource'=>self::RESOURCE,'grant_type'=>'authorization_code']);self::assertResponseStatusCodeSame(400);self::assertSame('invalid_target',json_decode($browser->getResponse()->getContent(),true)['error']);
    }

    public function testAnonymousAuthorizationReturnsThroughRealStudioLoginAndKeepsState():void
    {
        $browser=self::createClient();$owner=$this->account();$id=$this->registration();$query=$this->authorization($id);
        $browser->request('GET','/oauth2/compose/authorize?'.http_build_query($query));self::assertResponseRedirects();self::assertStringStartsWith('/login?',$browser->getResponse()->headers->get('Location'));
        $browser->followRedirect();self::assertSelectorTextContains('h1','Sign in to your studio');$browser->submitForm('Sign in',['_username'=>$owner->getEmail(),'_password'=>'Test password 2026!']);self::assertResponseRedirects();self::assertStringContainsString('/oauth2/compose/authorize?',$browser->getResponse()->headers->get('Location'));
        $browser->followRedirect();self::assertResponseRedirects();self::assertStringStartsWith('/oauth2/compose/approval/',$browser->getResponse()->headers->get('Location'));$browser->followRedirect();$browser->submitForm('Allow composer access');$browser->followRedirect();self::assertResponseRedirects();$location=$browser->getResponse()->headers->get('Location');self::assertStringStartsWith(self::REDIRECT.'?',$location);parse_str(parse_url($location,PHP_URL_QUERY),$callback);self::assertSame($query['state'],$callback['state']);self::assertSame('http://127.0.0.1:8095',$callback['iss']);
        $tokens=$this->exchange($browser,['grant_type'=>'authorization_code','client_id'=>$id,'redirect_uri'=>self::REDIRECT,'resource'=>self::RESOURCE,'code'=>$callback['code'],'code_verifier'=>str_repeat('v',64)]);self::assertResponseIsSuccessful();$this->mcp($browser,$tokens['access_token']);self::assertResponseIsSuccessful();
    }

}
