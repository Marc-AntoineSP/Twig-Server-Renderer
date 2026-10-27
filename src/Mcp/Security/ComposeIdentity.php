<?php
namespace App\Mcp\Security;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
final readonly class ComposeIdentity implements UserInterface
{
    public function __construct(public User $owner,public array $scopes,public array $patterns,public string $credentialId){}
    public function getUserIdentifier():string{return 'compose:'.$this->owner->getId().':'.$this->credentialId;}
    public function getRoles():array{return $this->owner->getRoles();}
    public function eraseCredentials():void{}
    public function permits(string $scope):bool{return $this->owner->isEnabled()&&in_array($scope,$this->scopes,true);}
    public function matches(string $name):bool{foreach($this->patterns as $pattern)if($pattern==='*'||$pattern===$name||str_ends_with($pattern,'/*')&&str_starts_with($name,substr($pattern,0,-1)))return true;return false;}
}
