<?php

namespace App\Twig;

use App\Favorites\Favorites;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FavoriteExtension extends AbstractExtension
{
    public function __construct(private readonly Favorites $favorites, private readonly RequestStack $requests, private readonly UrlGeneratorInterface $urls) {}
    public function getFunctions(): array { return [new TwigFunction('is_favorite', $this->favorites->contains(...)), new TwigFunction('listing_return', $this->back(...))]; }
    public function back(string $route): string
    {
        $fallback = $this->urls->generate($route);
        $candidate = $this->requests->getCurrentRequest()?->query->get('returnTo', '');
        $parts = parse_url($candidate);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || preg_match('/[\x00-\x20\\\\]/', $candidate) || ($parts['path'] ?? '') !== $fallback) return $fallback;
        return $candidate;
    }
}
