<?php
namespace App\Mcp\Security;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\ResourceServer;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use Omouren\McpOauthBundle\OAuth\{JwtClaimReader,ResourceAudienceValidator};
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\{AuthenticationException,CustomUserMessageAuthenticationException};
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\{Passport,SelfValidatingPassport};

final class ComposeAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ComposeTokens $tokens,private readonly ResourceServer $oauth,private readonly ClientManagerInterface $clients,private readonly HttpMessageFactoryInterface $psr,private readonly EntityManagerInterface $em,private readonly JwtClaimReader $claims,private readonly ResourceAudienceValidator $audiences,#[Autowire('%env(MCP_PUBLIC_BASE_URL)%')]private readonly string $base){}
    public function supports(Request $request):?bool{return true;}
    public function authenticate(Request $request):Passport
    {
        $header=$request->headers->get('Authorization','');$plain=str_starts_with($header,'Bearer ')?substr($header,7):'';$identity=null;
        if(str_starts_with($plain,'sgc_'))$identity=$this->tokens->verify($plain);
        elseif($plain!=='') {
            try {
                // League verifies the signature, time constraints and server-side revocation.
                $verified=$this->oauth->validateAuthenticatedRequest($this->psr->createRequest($request));
                $claims=$this->claims->payload($plain)??[];
                if(!$this->audiences->tokenTargetsResource($claims,rtrim($this->base,'/').'/mcp/compose'))throw new \RuntimeException('Wrong resource.');
                if(!$this->clients->find((string)$verified->getAttribute('oauth_client_id'))?->isActive())throw new \RuntimeException('Inactive OAuth client.');
                $sub=$verified->getAttribute('oauth_user_id');
                $owner=is_string($sub)?$this->em->getRepository(User::class)->findOneBy(['email'=>$sub]):null;
                if(!$owner&&is_string($sub))$owner=$this->em->find(User::class,$sub);
                if($owner?->isEnabled())$identity=new ComposeIdentity($owner,(array)$verified->getAttribute('oauth_scopes'),['*'],'oauth:'.$verified->getAttribute('oauth_client_id'));
            }catch(\Throwable){$identity=null;}
        }
        if(!$identity||!array_intersect($identity->owner->getRoles(),['ROLE_EDITOR','ROLE_ADMIN']))throw new CustomUserMessageAuthenticationException('An active composer credential for an enabled editor is required.');
        return new SelfValidatingPassport(new UserBadge($identity->getUserIdentifier(),static fn()=>$identity));
    }
    public function onAuthenticationSuccess(Request $request,TokenInterface $token,string $firewallName):?Response{return null;}
    public function onAuthenticationFailure(Request $request,AuthenticationException $exception):?Response
    {
        return new JsonResponse(['error'=>'invalid_token','message'=>'An active composer credential is required.'],401,['Cache-Control'=>'private, no-store','WWW-Authenticate'=>'Bearer resource_metadata="'.rtrim($this->base,'/').'/.well-known/oauth-protected-resource/mcp/compose", scope="compose:read"']);
    }
}
