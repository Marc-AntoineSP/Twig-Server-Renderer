<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ThemeUpdateInput
{
    public function __construct(#[Assert\Type(\stdClass::class)] #[Assert\NotNull] public mixed $document, #[Assert\Positive] public ?int $expectedRevision = null)
    {
    }
}
