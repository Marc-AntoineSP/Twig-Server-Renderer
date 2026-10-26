<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'catalogue_release')]
#[ORM\UniqueConstraint(name: 'release_version_unique', columns: ['item_id', 'version'])]
class Release
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(type: Types::JSONB_OBJECT)]
    private \stdClass $manifestData;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private CatalogueItem $item,
        #[ORM\Column(length: 32)]
        private string $version,
        \stdClass $manifestData,
        #[ORM\Column(length: 64)]
        private string $archiveHash,
        #[ORM\Column(length: 240)]
        private string $archiveKey,
    ) {
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version) || strlen($version) > 32) {
            throw new \InvalidArgumentException('A stable x.y.z version is required.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $archiveHash) || !preg_match('/^[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_.-]+)*$/D', $archiveKey) || str_contains($archiveKey, '..')) {
            throw new \InvalidArgumentException('Invalid release archive metadata.');
        }
        $this->id = (string) new Ulid();
        $this->manifestData = json_decode(json_encode($manifestData, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getItem(): CatalogueItem { return $this->item; }
    public function getVersion(): string { return $this->version; }
    public function getManifestData(): \stdClass { return json_decode(json_encode($this->manifestData, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR); }
    public function getArchiveHash(): string { return $this->archiveHash; }
    public function getArchiveKey(): string { return $this->archiveKey; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
