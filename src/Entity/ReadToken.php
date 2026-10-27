<?php
namespace App\Entity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
#[ORM\Entity]
#[ORM\UniqueConstraint(name:'read_token_hash_unique',columns:['token_hash'])]
class ReadToken
{
    #[ORM\Id,ORM\Column(length:26)] private string $id;
    #[ORM\Column(type:Types::DATETIMETZ_IMMUTABLE,nullable:true)] private ?\DateTimeImmutable $revokedAt=null;
    #[ORM\Column(type:Types::DATETIMETZ_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne,ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private User $owner,
        #[ORM\Column(length:100)] private string $label,
        #[ORM\Column(length:64)] private string $tokenHash,
        #[ORM\Column(type:Types::JSONB)] private array $scopes,
        #[ORM\Column(type:Types::DATETIMETZ_IMMUTABLE)] private \DateTimeImmutable $expiresAt,
    ) {$this->id=(string)new Ulid();$this->createdAt=new \DateTimeImmutable();}
    public function getId():string{return $this->id;}
    public function getOwner():User{return $this->owner;}
    public function getLabel():string{return $this->label;}
    public function getScopes():array{return $this->scopes;}
    public function getExpiresAt():\DateTimeImmutable{return $this->expiresAt;}
    public function getRevokedAt():?\DateTimeImmutable{return $this->revokedAt;}
    public function revoke():void{$this->revokedAt??=new \DateTimeImmutable();}
    public function isActive():bool{return null===$this->revokedAt && $this->expiresAt>new \DateTimeImmutable() && $this->owner->isEnabled();}
    public function allows(string $name):bool
    {
        foreach($this->scopes as $scope) if('*'===$scope || $scope===$name || (str_ends_with($scope,'/*') && str_starts_with($name,substr($scope,0,-1)))) return true;
        return false;
    }
}
