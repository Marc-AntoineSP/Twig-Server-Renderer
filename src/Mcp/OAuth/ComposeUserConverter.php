<?php
namespace App\Mcp\OAuth;
use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Converter\UserConverterInterface;
use League\Bundle\OAuth2ServerBundle\Entity\User as OAuthUser;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Security\Core\User\UserInterface;

/** Stable subject IDs avoid exposing emails and the OAuth library's 128-byte subject cap. */
#[AsDecorator('league.oauth2_server.converter.user')]
final readonly class ComposeUserConverter implements UserConverterInterface
{
    public function toLeague(UserInterface $user):UserEntityInterface
    {
        if(!$user instanceof User)throw new \LogicException('An application user is required.');
        $subject=new OAuthUser();$subject->setIdentifier($user->getId());return $subject;
    }
}
