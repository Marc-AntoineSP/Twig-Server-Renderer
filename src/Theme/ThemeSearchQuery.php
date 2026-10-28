<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ThemeSearchQuery
{
    public function __construct(
        #[Assert\Length(max: 120)] public string $q = '',
        #[Assert\Choice(choices: ['', 'light', 'dark'])] public string $mode = '',
        #[Assert\Choice(choices: ['updated', 'name'])] public string $sort = 'updated',
        #[Assert\Range(min: 1, max: 100000)] public int $page = 1,
        public bool $favorites = false,
    ) {}
}
