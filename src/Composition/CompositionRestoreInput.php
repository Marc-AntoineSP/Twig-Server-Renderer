<?php
namespace App\Composition;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class CompositionRestoreInput
{
    public function __construct(#[Assert\Positive] public int $expectedRevision, #[Assert\Positive] public int $restoreRevision) {}
}
