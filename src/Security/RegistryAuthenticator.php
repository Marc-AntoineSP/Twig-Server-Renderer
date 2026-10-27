<?php
namespace App\Security;
use App\Registry\RegistryIdentity;
use App\Registry\Tokens;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Uid\Ulid;
final class RegistryAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly Tokens $tokens){}
    public function supports(Request $request):?bool{return true;}
    public function authenticate(Request $request):Passport
    {
        $header=$request->headers->get('Authorization','');
        $token=str_starts_with($header,'Bearer ')?$this->tokens->verify(substr($header,7)):null;
        if(!$token) throw new CustomUserMessageAuthenticationException('A valid scoped registry token is required.');
        return new SelfValidatingPassport(new UserBadge('registry:'.$token->getId(),static fn()=>new RegistryIdentity($token)));
    }
    public function onAuthenticationSuccess(Request $request,TokenInterface $token,string $firewallName):?Response{return null;}
    public function onAuthenticationFailure(Request $request,AuthenticationException $exception):?Response
    {
        return new JsonResponse(['code'=>'registry_authentication_required','message'=>'A valid scoped registry token is required.','fieldErrors'=>(object)[],'requestId'=>(string)new Ulid()],401,['Cache-Control'=>'private, no-store','WWW-Authenticate'=>'Bearer realm="styleguide-registry"']);
    }
}
