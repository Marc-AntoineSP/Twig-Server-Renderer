<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'theme_name_unique', columns: ['name'])]
class Theme
{
    #[ORM\Id]
    #[ORM\Column(length: 26)] private string $id;
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)] private int $revision = 1;
    #[ORM\Column(type: Types::JSONB_OBJECT)] private \stdClass $document;
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)] private \DateTimeImmutable $updatedAt;

    public function __construct(#[ORM\Column(length: 100)] private string $name, \stdClass $document)
    {
        $this->id = (string) new Ulid();
        $this->replace($document);
    }

    public function replace(\stdClass $document): void
    {
        if ($document->name !== $this->name) { throw new \InvalidArgumentException('Theme name is immutable.'); }
        $this->document = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getRevision(): int { return $this->revision; }
    public function getDocument(): \stdClass { return json_decode(json_encode($this->document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR); }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
