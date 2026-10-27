<?php
namespace App\Registry;
use App\Entity\ReadToken;
use Symfony\Component\Security\Core\User\UserInterface;
final readonly class RegistryIdentity implements UserInterface
{
    public function __construct(public ReadToken $token){}
    public function getRoles():array{return ['ROLE_READER'];}
    public function getUserIdentifier():string{return 'registry:'.$this->token->getId();}
}
