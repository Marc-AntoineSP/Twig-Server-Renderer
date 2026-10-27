<?php
namespace App\Composition;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class CompositionCommandInput
{
    public function __construct(#[Assert\Type(\stdClass::class),Assert\NotNull]public mixed $document,#[Assert\Positive]public int $expectedRevision,#[Assert\Count(min:1,max:50)]public array $commands){}
}
