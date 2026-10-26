<?php

namespace App\Catalogue;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DraftInput
{
    public function __construct(
        #[Assert\Type(\stdClass::class)]
        #[Assert\NotNull]
        public mixed $manifestData,
        #[Assert\Type(\stdClass::class)]
        #[Assert\NotNull]
        public mixed $sourceMap,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $title,
        #[Assert\Length(max: 2000)]
        public string $description = '',
        #[Assert\Count(max: 30)]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(min: 1, max: 40), new Assert\Regex('/^[a-zA-Z0-9][a-zA-Z0-9 -]*$/D')])]
        public array $tags = [],
    ) {
    }
}
