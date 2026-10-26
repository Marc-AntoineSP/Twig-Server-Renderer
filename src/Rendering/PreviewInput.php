<?php

namespace App\Rendering;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewInput
{
    public function __construct(
        #[Assert\Ulid] public string $itemId,
        #[Assert\Range(min: 1, max: 2147483647)] public int $sequence,
        #[Assert\NotBlank] #[Assert\Regex('/^[a-f0-9]{32}$/D')] public string $channel,
        public mixed $manifestData = null,
        public mixed $sourceMap = null,
        public mixed $props = null,
        #[Assert\Valid] public ?RendererSettings $preset = null,
    ) {
    }
}
