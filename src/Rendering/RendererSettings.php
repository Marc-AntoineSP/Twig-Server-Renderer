<?php

namespace App\Rendering;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RendererSettings
{
    public function __construct(
        #[Assert\Range(min: 320, max: 1920)] public int $width = 768,
        #[Assert\Choice(choices: [50, 75, 100])] public int $zoom = 100,
        #[Assert\Range(min: 12, max: 24)] public int $rootSize = 16,
        #[Assert\Choice(choices: ['system-sans', 'system-serif', 'system-mono'])] public string $bodyFont = 'system-sans',
        #[Assert\Choice(choices: ['system-sans', 'system-serif', 'system-mono'])] public string $headingFont = 'system-sans',
        #[Assert\Choice(choices: ['text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl'])] public string $textClass = 'text-base',
        public bool $scripts = true,
        public mixed $props = null,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
