<?php

namespace App\Catalogue;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchQuery
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public string $q = '',
        #[Assert\Choice(choices: ['', 'component', 'block', 'layout', 'theme'])]
        public string $kind = '',
        #[Assert\Choice(choices: ['updated', 'name'])]
        public string $sort = 'updated',
        #[Assert\Range(min: 1, max: 100000)]
        public int $page = 1,
    ) {
    }
}
