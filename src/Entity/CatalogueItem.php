<?php

namespace App\Entity;

use App\Repository\CatalogueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CatalogueRepository::class)]
#[ORM\UniqueConstraint(name: 'catalogue_name_unique', columns: ['namespace', 'slug'])]
#[ORM\Index(name: 'catalogue_updated_idx', columns: ['updated_at'])]
class CatalogueItem
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $namespace,
        #[ORM\Column(length: 100)]
        private string $slug,
        #[ORM\Column(length: 20)]
        private string $kind,
        #[ORM\Column(length: 120)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $description = '',
        #[ORM\Column(type: Types::JSONB)]
        private array $tags = [],
        #[ORM\Column(length: 20)]
        private string $editMode = 'source',
    ) {
        $this->id = (string) new Ulid();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reviseMetadata(string $title, string $description, array $tags): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->tags = $tags;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getNamespace(): string { return $this->namespace; }
    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->namespace.'/'.$this->slug; }
    public function getKind(): string { return $this->kind; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getTags(): array { return $this->tags; }
    public function getEditMode(): string { return $this->editMode; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
