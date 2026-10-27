<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class AppearanceResponseListener
{
    #[AsEventListener(event: 'kernel.response', priority: -20)]
    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && str_contains($event->getResponse()->headers->get('Content-Type', ''), 'text/html')) {
            $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
            $event->getResponse()->setVary('Cookie', false);
        }
    }
}
