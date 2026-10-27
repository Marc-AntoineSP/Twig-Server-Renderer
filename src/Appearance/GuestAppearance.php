<?php

namespace App\Appearance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final readonly class GuestAppearance
{
    public const COOKIE = 'studio_appearance';
    public function __construct(#[Autowire('%kernel.secret%')] private string $secret) {}
    public function mode(Request $request): string
    {
        $value = $request->cookies->get(self::COOKIE, '');
        $parts = explode('.', $value);
        return count($parts) === 2 && in_array($parts[0], ['light', 'dark', 'system'], true) && hash_equals(hash_hmac('sha256', 'appearance-v1.'.$parts[0], $this->secret), $parts[1]) ? $parts[0] : 'system';
    }
    public function cookie(string $mode, Request $request): Cookie
    {
        return Cookie::create(self::COOKIE, $mode.'.'.hash_hmac('sha256', 'appearance-v1.'.$mode, $this->secret))->withExpires(new \DateTimeImmutable('+1 year'))->withSecure($request->isSecure())->withHttpOnly(true)->withSameSite('lax')->withPath('/');
    }
}
