<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
#[ORM\Entity]
#[ORM\UniqueConstraint(name:'compose_token_hash',columns:['token_hash'])]
final class ComposeToken
{
    #[ORM\Id] #[ORM\Column(length:26)] private string $id;
    #[ORM\Column(type:'datetimetz_immutable',nullable:true)] private ?\DateTimeImmutable $revokedAt=null;
    #[ORM\Column(type:'datetimetz_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private User $owner,
        #[ORM\Column(length:100)] private string $label,
        #[ORM\Column(length:64)] private string $tokenHash,
        #[ORM\Column(type:'jsonb')] private array $scopes,
        #[ORM\Column(type:'jsonb')] private array $cataloguePatterns,
        #[ORM\Column(type:'datetimetz_immutable')] private \DateTimeImmutable $expiresAt,
    ){$this->id=(string)new Ulid();$this->createdAt=new \DateTimeImmutable();}
    public function getId():string{return $this->id;}
    public function getLabel():string{return $this->label;}
    public function getCreatedAt():\DateTimeImmutable{return $this->createdAt;}
    public function getExpiresAt():\DateTimeImmutable{return $this->expiresAt;}
    public function getRevokedAt():?\DateTimeImmutable{return $this->revokedAt;}
    public function getOwner():User{return $this->owner;}
    public function getScopes():array{return $this->scopes;}
    public function getCataloguePatterns():array{return $this->cataloguePatterns;}
    public function isActive():bool{return !$this->revokedAt&&$this->expiresAt>new \DateTimeImmutable()&&$this->owner->isEnabled();}
    public function revoke():void{$this->revokedAt=new \DateTimeImmutable();}
}
