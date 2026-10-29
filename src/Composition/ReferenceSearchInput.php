<?php

namespace App\Composition;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReferenceSearchInput
{
    public function __construct(
        #[Assert\Type(\stdClass::class)] #[Assert\NotNull] public mixed $document,
        #[Assert\Positive] public int $expectedRevision,
        #[Assert\Regex('/^n_[a-f0-9]{32}$/D')] public string $selectedNodeId,
        #[Assert\Choice(choices: ['insert', 'replace'])] public string $mode = 'insert',
        #[Assert\Valid] public ReferenceQuery $query = new ReferenceQuery(),
    ) {}
}
