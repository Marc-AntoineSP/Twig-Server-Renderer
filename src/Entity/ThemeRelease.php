<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Repository\ThemeReleaseRepository::class, readOnly: true)]
#[ORM\UniqueConstraint(name: 'theme_release_version_unique', columns: ['theme_id', 'version'])]
class ThemeRelease
{
    #[ORM\Id]
    #[ORM\Column(length: 26)] private string $id;
    #[ORM\Column(type: Types::JSONB_OBJECT)] private \stdClass $document;
    #[ORM\Column(type: Types::JSONB_OBJECT)] private \stdClass $files;
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)] private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Theme $theme,
        #[ORM\Column(length: 32)] private string $version,
        \stdClass $document,
        array $files,
    ) {
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version) || strlen($version) > 32) { throw new \InvalidArgumentException('Stable theme version required.'); }
        $this->id = (string) new Ulid();
        $this->document = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
        $this->files = (object) $files;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getTheme(): Theme { return $this->theme; }
    public function getVersion(): string { return $this->version; }
    public function getDocument(): \stdClass { return json_decode(json_encode($this->document, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR); }
    public function getFiles(): array { return (array) $this->files; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
