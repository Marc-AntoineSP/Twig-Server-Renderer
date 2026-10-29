<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContrastInput
{
    public function __construct(#[Assert\Type(\stdClass::class)] #[Assert\NotNull] public mixed $colors) {}
}
