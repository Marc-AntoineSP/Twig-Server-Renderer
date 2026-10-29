<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'theme_family_key_unique', columns: ['stable_key'])]
#[ORM\UniqueConstraint(name: 'uniq_family_light', columns: ['light_theme_id'])]
#[ORM\UniqueConstraint(name: 'uniq_family_dark', columns: ['dark_theme_id'])]
class ThemeFamily
{
    #[ORM\Id]
    #[ORM\Column(length: 26)] private string $id;
    #[ORM\Version]
    #[ORM\Column(type: 'integer')] private int $revision = 1;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?Theme $lightTheme = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?Theme $darkTheme = null;

    public function __construct(
        #[ORM\Column(name: 'stable_key', length: 100)] private string $key,
        #[ORM\Column(length: 120)] private string $label,
        #[ORM\Column] private bool $builtIn = false,
    ) { $this->id = (string) new Ulid(); }

    /** Transient read fallback. Never persist this object; explicit writes resolve a real family. */
    public static function legacy(Theme $theme): self
    {
        $family = new self($theme->getName(), $theme->getName());
        $family->id = $theme->getId(); $family->attach($theme);
        return $family;
    }

    public function getId(): string { return $this->id; }
    public function getKey(): string { return $this->key; }
    public function getLabel(): string { return $this->label; }
    public function getRevision(): int { return $this->revision; }
    public function isBuiltIn(): bool { return $this->builtIn; }
    public function rename(string $label): void { $this->label = $label; }
    public function getLightTheme(): ?Theme { return $this->lightTheme; }
    public function getDarkTheme(): ?Theme { return $this->darkTheme; }
    public function variants(): array { return array_filter(['light' => $this->lightTheme, 'dark' => $this->darkTheme]); }
    public function attach(Theme $theme): void
    {
        $mode = $theme->getDocument()->colorScheme;
        $property = $mode.'Theme';
        if ($this->$property && $this->$property->getId() !== $theme->getId()) throw new \InvalidArgumentException('This family already has a '.$mode.' variant.');
        $this->$property = $theme;
    }
}
