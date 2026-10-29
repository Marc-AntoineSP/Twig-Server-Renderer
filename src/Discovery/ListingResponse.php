<?php

namespace App\Discovery;

use Symfony\Component\HttpFoundation\{RedirectResponse, RequestStack, Response};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** One authorized SSR representation for direct visits and progressively enhanced lists. */
final readonly class ListingResponse
{
    public function __construct(private Environment $twig, private UrlGeneratorInterface $urls, private RequestStack $requests) {}

    public function render(string $route, string $template, array $data): Response
    {
        $requestId = $this->requests->getCurrentRequest()?->headers->get('X-Discovery-Request', '') ?? '';
        if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/D', $requestId)) $requestId = '';
        $headers = ['Cache-Control' => 'private, no-store', 'Vary' => 'Cookie, Turbo-Frame', 'X-Discovery-Request' => $requestId];
        // Clamp once on the server, including when removal empties the last page.
        if ($data['query']->page !== $data['page']) {
            return new RedirectResponse($this->urls->generate($route, [...get_object_vars($data['query']), 'page' => $data['page']]), 303, $headers);
        }

        return new Response($this->twig->render($template, $data + ['discoveryRequest' => $requestId]), headers: $headers);
    }
}
