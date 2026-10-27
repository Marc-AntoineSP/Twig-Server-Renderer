<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
#[ORM\Entity]
#[ORM\UniqueConstraint(name:'composition_revision_number',columns:['item_id','revision'])]
final class CompositionRevision
{
    #[ORM\Id] #[ORM\Column(length:26)] private string $id;
    #[ORM\Column(type:'datetimetz_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private CatalogueItem $item,
        #[ORM\Column] private int $revision,
        #[ORM\Column(type:'jsonb_object')] private \stdClass $document,
        #[ORM\Column(length:120)] private string $title,
        #[ORM\Column(type:'text')] private string $description,
        #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete:'SET NULL')] private ?User $actor,
        #[ORM\Column(length:200)] private string $summary,
    ){$this->id=(string)new Ulid();$this->createdAt=new \DateTimeImmutable();}
    public function getRevision():int{return $this->revision;}
    public function getDocument():\stdClass{return json_decode(json_encode($this->document,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);}
    public function getTitle():string{return $this->title;}
    public function getDescription():string{return $this->description;}
    public function describe():array{return ['revision'=>$this->revision,'summary'=>$this->summary,'createdAt'=>$this->createdAt->format(DATE_ATOM),'actorId'=>$this->actor?->getId()];}
}
