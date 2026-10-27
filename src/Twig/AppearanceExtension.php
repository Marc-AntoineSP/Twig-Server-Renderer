<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppearanceExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('application_appearance', [AppearanceRuntime::class, 'appearance']), new TwigFunction('application_theme_families', [AppearanceRuntime::class, 'choices']), new TwigFunction('theme_family', [AppearanceRuntime::class, 'family'])];
    }
}
