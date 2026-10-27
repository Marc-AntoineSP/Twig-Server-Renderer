<?php
namespace App\Packaging;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class ExportInput
{
    public function __construct(#[Assert\Ulid]public string $releaseId,#[Assert\Choice(choices:['source','collection','static','json','yaml','theme'])]public string $format='source',#[Assert\Ulid]public ?string $themeReleaseId=null,#[Assert\Type(\stdClass::class)]public mixed $props=null){}
}
