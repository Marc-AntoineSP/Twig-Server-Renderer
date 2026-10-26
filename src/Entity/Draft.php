<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Draft
{
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $revision = 1;

    #[ORM\Column(type: Types::JSONB_OBJECT)]
    private \stdClass $manifestData;

    #[ORM\Column(type: Types::JSONB_OBJECT)]
    private \stdClass $sourceMap;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CatalogueItem $item,
        \stdClass $manifestData,
        \stdClass $sourceMap,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $updatedBy = null,
    ) {
        $this->replace($manifestData, $sourceMap, $updatedBy);
    }

    public function replace(\stdClass $manifestData, \stdClass $sourceMap, ?User $updatedBy): void
    {
        $this->manifestData = self::copy($manifestData);
        $this->sourceMap = self::copy($sourceMap);
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function copy(\stdClass $value): \stdClass
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
    }

    public function getItem(): CatalogueItem { return $this->item; }
    public function getRevision(): int { return $this->revision; }
    public function getManifestData(): \stdClass { return self::copy($this->manifestData); }
    public function getSourceMap(): \stdClass { return self::copy($this->sourceMap); }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }
}
