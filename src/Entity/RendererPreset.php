<?php

namespace App\Entity;

use App\Rendering\RendererSettings;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'renderer_workspace_unique', columns: ['owner_id', 'item_id'])]
class RendererPreset
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;
    #[ORM\Column(type: Types::JSONB_OBJECT)]
    private \stdClass $settings;
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;
    #[ORM\Column(length: 80)]
    private string $name = 'Workspace';

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CatalogueItem $item,
        RendererSettings $settings,
    ) {
        $this->id = (string) new Ulid();
        $this->replace($settings);
    }

    public function replace(RendererSettings $settings): void
    {
        $this->settings = json_decode(json_encode($settings->toArray(), JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSettings(): \stdClass
    {
        return json_decode(json_encode($this->settings, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
    }
}
