<?php
namespace App\Registry;
use App\Entity\ReadToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
final readonly class Tokens
{
    public function __construct(private EntityManagerInterface $em){}
    public function issue(User $owner,string $label,array $scopes,int $days=30):array
    {
        if(!in_array('ROLE_ADMIN',$owner->getRoles(),true)) throw new CatalogueException(403,'An enabled administrator owns registry tokens.');
        if(!$owner->isEnabled() || ''===trim($label) || mb_strlen($label)>100 || !$scopes || count($scopes)>20 || $days<1 || $days>365) throw new CatalogueException(422,'Choose a label, 1–20 scopes and 1–365 days.');
        foreach($scopes as $scope) if(!is_string($scope) || strlen($scope)>100 || !preg_match('~^(?:\*|[a-z][a-z0-9-]*/(?:\*|[a-z][a-z0-9-]*))$~D',$scope)) throw new CatalogueException(422,'Scopes use namespace/slug, namespace/* or explicit *.');
        $plain='sgt_'.bin2hex(random_bytes(32));$token=new ReadToken($owner,trim($label),hash('sha256',$plain),array_values(array_unique($scopes)),new \DateTimeImmutable('+'.$days.' days'));
        $this->em->persist($token);$this->em->flush();return [$token,$plain];
    }
    public function verify(string $plain):?ReadToken
    {
        if(!preg_match('/^sgt_[a-f0-9]{64}$/D',$plain)) return null;
        $token=$this->em->getRepository(ReadToken::class)->findOneBy(['tokenHash'=>hash('sha256',$plain)]);
        return $token?->isActive()?$token:null;
    }
    public function revoke(ReadToken $token):void{$token->revoke();$this->em->flush();}
}
