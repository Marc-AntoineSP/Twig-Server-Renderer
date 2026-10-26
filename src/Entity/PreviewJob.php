<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Index(name: 'preview_owner_status_idx', columns: ['owner_id', 'status', 'expires_at'])]
class PreviewJob
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;
    #[ORM\Column(length: 20)]
    private string $status = 'queued';
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(type: Types::JSONB, nullable: true)]
    private ?array $error = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        #[ORM\Column] private int $requestSequence,
        #[ORM\Column(length: 64)] private string $inputHash,
        #[ORM\Column(type: Types::JSONB_OBJECT)] private \stdClass $payload,
    ) {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+30 minutes');
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getRequestSequence(): int { return $this->requestSequence; }
    public function getInputHash(): string { return $this->inputHash; }
    public function getStatus(): string { return $this->expiresAt < new \DateTimeImmutable() ? 'expired' : $this->status; }
    public function getError(): ?array { return $this->error; }
    public function getPayload(): array { return json_decode(json_encode($this->payload, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR); }
}
