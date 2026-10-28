<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/** Stable catalogue identities, never a draft revision, release or theme family. */
#[ORM\Entity]
#[ORM\Table(name: 'personal_favorite')]
#[ORM\UniqueConstraint(name: 'favorite_user_theme_unique', columns: ['owner_id', 'theme_id'])]
#[ORM\UniqueConstraint(name: 'favorite_user_item_unique', columns: ['owner_id', 'catalogue_item_id'])]
class Favorite
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Theme $theme = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?CatalogueItem $catalogueItem = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        Theme|CatalogueItem $target,
    ) {
        $this->id = (string) new Ulid();
        if ($target instanceof Theme) $this->theme = $target;
        else $this->catalogueItem = $target;
    }
}
