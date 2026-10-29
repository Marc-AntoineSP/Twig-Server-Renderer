<?php

namespace App\Rendering;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CataloguePreviewInput
{
    public function __construct(
        #[Assert\Range(min: 1, max: 2147483647)] public int $sequence,
        #[Assert\NotBlank] #[Assert\Regex('/^[a-f0-9]{32}$/D')] public string $channel,
        #[Assert\Ulid] public ?string $releaseId = null,
        #[Assert\Positive] public ?int $draftRevision = null,
        #[Assert\Range(min: -1, max: 19)] public int $example = -1,
        public mixed $props = null,
        #[Assert\Valid] public ?RendererSettings $preset = null,
    ) {}
}
