<?php

namespace App\Font;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FontPairingInput
{
    public function __construct(
        #[Assert\Type(\stdClass::class)] #[Assert\NotNull] public mixed $typography,
        #[Assert\Length(max: 100)] public string $text = '',
        #[Assert\Choice(choices: ['light', 'dark'])] public string $scheme = 'light',
    ) {}
}
