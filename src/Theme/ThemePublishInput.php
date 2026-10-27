<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ThemePublishInput
{
    public function __construct(#[Assert\Positive] public int $expectedRevision)
    {
    }
}
