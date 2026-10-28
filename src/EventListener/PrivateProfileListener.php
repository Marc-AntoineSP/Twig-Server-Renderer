<?php
namespace App\EventListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class PrivateProfileListener
{
    public function __construct(#[Autowire(service:'profiler')] private ?Profiler $profiler=null){}
    #[AsEventListener(event:'kernel.response',priority:0)]
    public function __invoke(ResponseEvent $event):void
    {
        $path=$event->getRequest()->getPathInfo();
        if(str_starts_with($path,'/profile') || str_starts_with($path,'/api/v1/registry/') || str_starts_with($path,'/registry-tokens') || str_starts_with($path,'/mcp/compose') || str_starts_with($path,'/oauth2/compose')) $this->profiler?->disable();
    }
}
