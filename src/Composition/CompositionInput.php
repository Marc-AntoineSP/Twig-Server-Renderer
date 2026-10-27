<?php
namespace App\Composition;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class CompositionInput
{
    public function __construct(#[Assert\Type(\stdClass::class), Assert\NotNull] public mixed $document, #[Assert\Positive] public int $expectedRevision, #[Assert\Length(min: 1, max: 120)] public ?string $title = null, #[Assert\Length(max: 2000)] public ?string $description = null) {}
}
