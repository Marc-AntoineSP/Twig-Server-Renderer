<?php
namespace App\Mcp\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
#[AsEventListener(event:KernelEvents::REQUEST,priority:128)]
final readonly class ComposeRequestGuard
{
    public function __construct(#[Autowire('%env(bool:MCP_COMPOSE_ENABLED)%')]private bool $enabled,#[Autowire('%env(MCP_PUBLIC_BASE_URL)%')]private string $base,#[Autowire('%env(MCP_PUBLIC_HOST)%')]private string $publicHost){
        $host=parse_url($base,PHP_URL_HOST);$scheme=parse_url($base,PHP_URL_SCHEME);
        if(!$host||!in_array($scheme,['http','https'],true)||($scheme!=='https'&&!in_array($host,['localhost','127.0.0.1','studio'],true))||(parse_url($base,PHP_URL_PATH) && parse_url($base,PHP_URL_PATH)!=='/')||parse_url($base,PHP_URL_QUERY)||parse_url($base,PHP_URL_FRAGMENT)||parse_url($base,PHP_URL_USER))throw new \InvalidArgumentException('MCP_PUBLIC_BASE_URL must be an HTTPS origin, or a local development HTTP origin.');
    }
    public function __invoke(RequestEvent $event):void
    {
        if(!$event->isMainRequest())return;$r=$event->getRequest();$path=$r->getPathInfo();
        if(!preg_match('~^(?:/mcp/compose/?$|/oauth2/compose(?:/|$)|/\.well-known/oauth-(?:authorization-server|protected-resource)(?:/|$))~',$path))return;
        if(!$this->enabled){$event->setResponse(new JsonResponse(['error'=>'not_found'],404));return;}
        if(!in_array(strtolower($r->getHost()),['localhost','127.0.0.1','studio',strtolower($this->publicHost)],true)){$event->setResponse(new JsonResponse(['error'=>'invalid_host'],403));return;}
        if($r->headers->has('Origin')) {
            $origins=[rtrim($this->base,'/'),'http://localhost:8095','http://127.0.0.1:8095'];
            if(!in_array($r->headers->get('Origin'),$origins,true)){$event->setResponse(new JsonResponse(['error'=>'invalid_origin'],403));return;}
        }
        if((int)$r->headers->get('Content-Length',0)>262144||strlen($r->getContent())>262144)$event->setResponse(new JsonResponse(['error'=>'request_too_large'],413));
    }
}
