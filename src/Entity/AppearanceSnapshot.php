<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Index(name: 'idx_appearance_source', columns: ['theme_id'])]
#[ORM\Index(name: 'idx_appearance_owner', columns: ['owner_id'])]
class AppearanceSnapshot
{
    #[ORM\Column(type: Types::JSONB_OBJECT)] private \stdClass $document;
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)] private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id] #[ORM\Column(length: 64)] private string $id,
        #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?Theme $theme,
        #[ORM\Column(nullable: true)] private ?int $sourceRevision,
        \stdClass $document,
        #[ORM\Column(type: Types::TEXT)] private string $css,
        #[ORM\Column(length: 64)] private string $cssHash,
        #[ORM\Column(length: 10)] private string $kind = 'saved',
        #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'CASCADE')] private ?User $owner = null,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $expiresAt = null,
    ) {
        $this->document = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): string { return $this->id; }
    public function getTheme(): ?Theme { return $this->theme; }
    public function getSourceRevision(): ?int { return $this->sourceRevision; }
    public function getDocument(): \stdClass { return json_decode(json_encode($this->document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR); }
    public function getCss(): string { return $this->css; }
    public function getCssHash(): string { return $this->cssHash; }
    public function getKind(): string { return $this->kind; }
    public function getOwner(): ?User { return $this->owner; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
