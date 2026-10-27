<?php
namespace App\EventListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
final class CsrfFailureListener
{
    #[AsEventListener(event:'kernel.exception',priority:11)]
    public function __invoke(ExceptionEvent $event):void
    {
        if($event->getThrowable() instanceof InvalidCsrfTokenException && !str_starts_with($event->getRequest()->getPathInfo(),'/api/')) $event->setResponse(new Response('Invalid CSRF token. Reload the page and try again.',403,['Cache-Control'=>'private, no-store']));
    }
}
