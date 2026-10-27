<?php
namespace App\Security;
use App\Entity\{CatalogueItem,User};
use App\Mcp\Security\ComposeIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
final class CompositionVoter extends Voter
{
    protected function supports(string $attribute,mixed $subject):bool{return in_array($attribute,['COMPOSE_READ','COMPOSE_EDIT','COMPOSE_CREATE','COMPOSE_PREVIEW'],true)&&($subject instanceof CatalogueItem||is_string($subject)||$subject===null);}
    protected function voteOnAttribute(string $attribute,mixed $subject,TokenInterface $token,?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote=null):bool
    {
        $principal=$token->getUser();$user=$principal instanceof ComposeIdentity?$principal->owner:$principal;
        if(!$user instanceof User||!$user->isEnabled()||!array_intersect($user->getRoles(),['ROLE_EDITOR','ROLE_ADMIN']))return false;
        if(!$principal instanceof ComposeIdentity)return true;
        $scope=match($attribute){'COMPOSE_READ'=>'compose:read','COMPOSE_EDIT'=>'compose:edit','COMPOSE_CREATE'=>'compose:create','COMPOSE_PREVIEW'=>'compose:preview'};
        $name=$subject instanceof CatalogueItem?$subject->getName():$subject;
        return $principal->permits($scope)&&($name===null||$principal->matches($name));
    }
}
