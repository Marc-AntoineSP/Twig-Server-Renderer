<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ThemeImportInput
{
    public function __construct(
        #[Assert\Choice(choices: ['json', 'yaml', 'css'])] public string $format,
        #[Assert\NotBlank] #[Assert\Length(max: 262144)] public string $content,
        #[Assert\Choice(choices: ['validate', 'apply'])] public string $mode = 'validate',
        #[Assert\Positive] public ?int $expectedRevision = null,
    ) {
    }
}
