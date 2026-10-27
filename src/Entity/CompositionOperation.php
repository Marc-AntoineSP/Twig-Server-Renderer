<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
#[ORM\Entity]
#[ORM\UniqueConstraint(name:'composition_operation_key',columns:['owner_id','operation','target','operation_id'])]
final class CompositionOperation
{
    #[ORM\Id] #[ORM\Column(length:26)] private string $id;
    #[ORM\Column(type:'datetimetz_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private User $owner,
        #[ORM\Column(length:64)] private string $operation,
        #[ORM\Column(length:100)] private string $target,
        #[ORM\Column(length:64)] private string $operationId,
        #[ORM\Column(length:64)] private string $payloadHash,
        #[ORM\Column(type:'jsonb_object')] private \stdClass $result,
    ){$this->id=(string)new Ulid();$this->createdAt=new \DateTimeImmutable();}
    public function getPayloadHash():string{return $this->payloadHash;}
    public function getResult():array{return get_object_vars(json_decode(json_encode($this->result,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR));}
}
