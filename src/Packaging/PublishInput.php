<?php
namespace App\Packaging;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class PublishInput
{
    public function __construct(
        #[Assert\Regex('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D'), Assert\Length(max: 32)] public string $version,
        #[Assert\Positive] public int $expectedRevision,
    ) {}
}
