<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_preference_family', columns: ['family_id'])]
#[ORM\Index(name: 'idx_preference_light_snapshot', columns: ['light_snapshot_id'])]
#[ORM\Index(name: 'idx_preference_dark_snapshot', columns: ['dark_snapshot_id'])]
class AppearancePreference
{
    #[ORM\Version] #[ORM\Column(type: 'integer')] private int $revision = 1;
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?ThemeFamily $family = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?AppearanceSnapshot $lightSnapshot = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?AppearanceSnapshot $darkSnapshot = null;
    #[ORM\Column(length: 6)] private string $mode = 'system';

    public function __construct(#[ORM\Id] #[ORM\OneToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner) {}
    public function getOwner(): User { return $this->owner; }
    public function getRevision(): int { return $this->revision; }
    public function getFamily(): ?ThemeFamily { return $this->family; }
    public function getMode(): string { return $this->mode; }
    public function snapshots(): array { return array_filter(['light' => $this->lightSnapshot, 'dark' => $this->darkSnapshot]); }
    public function select(ThemeFamily $family, array $snapshots, string $mode): void
    {
        $this->family = $family; $this->lightSnapshot = $snapshots['light'] ?? null; $this->darkSnapshot = $snapshots['dark'] ?? null; $this->mode = $mode;
    }
}
