<?php

namespace App\Rendering;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RendererSettings
{
    public function __construct(
        #[Assert\Range(min: 320, max: 1920)] public int $width = 768,
        #[Assert\Choice(choices: [50, 75, 100])] public int $zoom = 100,
        #[Assert\Range(min: 12, max: 24)] public int $rootSize = 16,
        #[Assert\Regex('/^[a-z][a-z0-9-]{0,79}$/D')] public string $bodyFont = 'system-sans',
        #[Assert\Regex('/^[a-z][a-z0-9-]{0,79}$/D')] public string $headingFont = 'system-sans',
        #[Assert\Choice(choices: ['text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl'])] public string $textClass = 'text-base',
        public bool $scripts = true,
        public mixed $props = null,
        #[Assert\Ulid] public ?string $themeReleaseId = null,
        #[Assert\Choice(choices: ['theme','light','dark'])] public string $colorScheme = 'theme',
        #[Assert\Choice(choices: ['base-100','base-200','transparent'])] public string $surface = 'base-100',
        public bool $deviceFrame = false,
        #[Assert\Regex('/^[a-z][a-z0-9-]{0,79}$/D')] public string $codeFont = 'theme',
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
