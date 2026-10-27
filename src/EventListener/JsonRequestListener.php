<?php
namespace App\EventListener;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
final class JsonRequestListener
{
    #[AsEventListener(event: 'kernel.request', priority: 8)]
    public function __invoke(RequestEvent $event): void
    {
        $request=$event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(),'/api/') || !str_contains($request->headers->get('Content-Type',''),'application/json')) return;
        if ('' === $request->getContent()) return;
        try { StrictJson::decode($request->getContent(),4194304); }
        catch(CatalogueException $e) { throw new CatalogueException(413 === $e->getStatusCode() ? 413 : 400,$e->getMessage(),[],$e->errorCode); }
    }
}
