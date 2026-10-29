<?php

namespace App\Font;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FontSearchQuery
{
    public function __construct(
        #[Assert\Length(max: 120)] public string $q = '',
        #[Assert\Choice(choices: ['', 'sans', 'serif', 'mono', 'display'])] public string $category = '',
        #[Assert\Range(min: 1, max: 100000)] public int $page = 1,
    ) {}
}
