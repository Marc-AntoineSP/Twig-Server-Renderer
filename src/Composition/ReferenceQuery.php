<?php

namespace App\Composition;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReferenceQuery
{
    public function __construct(
        #[Assert\Length(max: 100)] public string $q = '',
        #[Assert\Choice(choices: ['', 'component', 'block', 'layout'])] public string $kind = '',
        public bool $favorites = false,
        #[Assert\Length(max: 32)] #[Assert\Regex('/^(?:|(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))$/D')] public string $version = '',
        #[Assert\Range(min: 1, max: 10000)] public int $page = 1,
    ) {}
}
