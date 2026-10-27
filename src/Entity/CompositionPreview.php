<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
final class CompositionPreview
{
    public function __construct(
        #[ORM\Id] #[ORM\OneToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private PreviewJob $job,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private CatalogueItem $item,
        #[ORM\Column] private int $revision,
    ){}
    public function getJob():PreviewJob{return $this->job;}
    public function getItem():CatalogueItem{return $this->item;}
    public function getRevision():int{return $this->revision;}
}
