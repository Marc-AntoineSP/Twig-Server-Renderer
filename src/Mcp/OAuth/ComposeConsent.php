<?php
namespace App\Mcp\OAuth;
use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\{JsonResponse,RedirectResponse,RequestStack};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Bind each approval to this actor and the exact already-validated authorization query. */
#[AsEventListener(event:OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE,priority:128)]
final readonly class ComposeConsent
{
    public function __construct(private RequestStack $requests,private AuthorizationCheckerInterface $auth,private UrlGeneratorInterface $urls){}
    public function __invoke(AuthorizationRequestResolveEvent $event):void
    {
        $r=$this->requests->getCurrentRequest();if(!$r||$r->getPathInfo()!=='/oauth2/compose/authorize')return;
        $user=$event->getUser();
        if(!$user instanceof User||!$user->isEnabled()||!$this->auth->isGranted('ROLE_EDITOR')){$event->setResponse(new JsonResponse(['error'=>'access_denied'],403));return;}
        if($event->getCodeChallengeMethod()!=='S256'||!preg_match('/^[A-Za-z0-9_-]{43}$/D',$event->getCodeChallenge()??'')){$event->setResponse(new JsonResponse(['error'=>'invalid_request','error_description'=>'S256 PKCE is required.'],400));return;}
        $query=$r->query->all();$consentId=$query['_compose_consent']??null;unset($query['_compose_consent']);ksort($query);
        $hash=hash('sha256',json_encode($query,JSON_THROW_ON_ERROR));$session=$r->getSession();
        $pending=$session->get('compose.oauth.pending',[]);
        if(is_string($consentId)&&isset($pending[$consentId])) {
            $entry=$pending[$consentId];unset($pending[$consentId]);$session->set('compose.oauth.pending',$pending);
            if($entry['owner']===$user->getId()&&$entry['expires']>=time()&&hash_equals($entry['hash'],$hash)&&($entry['approved']??false)){$event->resolveAuthorization(true);return;}
            $event->setResponse(new JsonResponse(['error'=>'access_denied','error_description'=>'Consent expired or no longer matches this authorization request.'],403));return;
        }
        $pending=array_filter($pending,static fn($v)=>$v['expires']>=time());if(count($pending)>=10)array_shift($pending);
        $id=bin2hex(random_bytes(24));$pending[$id]=['owner'=>$user->getId(),'hash'=>$hash,'query'=>$query,'expires'=>time()+600,'approved'=>false,'clientName'=>$event->getClient()->getName(),'scopes'=>array_map('strval',$event->getScopes())];$session->set('compose.oauth.pending',$pending);
        $event->setResponse(new RedirectResponse($this->urls->generate('compose_oauth_consent',['id'=>$id])));
    }
}
