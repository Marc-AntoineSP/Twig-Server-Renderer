<?php
namespace App\Mcp\OAuth;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
#[AsEventListener(event:KernelEvents::REQUEST,priority:9)]
final readonly class ResourceRequestGuard
{
    public function __construct(#[Autowire('%env(MCP_PUBLIC_BASE_URL)%')]private string $base){}
    public function __invoke(RequestEvent $event):void
    {
        $r=$event->getRequest();if(!$event->isMainRequest()||!in_array($r->getPathInfo(),['/oauth2/compose/authorize','/oauth2/compose/token'],true))return;
        $values=$r->isMethod('GET')?$r->query:$r->request;
        // Require an explicit exact resource on both authorization and token exchange.
        // The maintained bundle binds this same value into its signed access token.
        if($values->get('resource')!==rtrim($this->base,'/').'/mcp/compose'||($r->query->has('resource')&&$r->query->get('resource')!==rtrim($this->base,'/').'/mcp/compose'))$event->setResponse(new JsonResponse(['error'=>'invalid_target','error_description'=>'Use the canonical MCP resource URL from discovery.'],400));
    }
}
