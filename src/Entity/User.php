<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'studio_user')]
#[ORM\UniqueConstraint(name: 'user_email_unique', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(type: Types::JSONB)]
    private array $roles;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email,
        string $role = 'ROLE_READER',
        #[ORM\Column]
        private bool $enabled = true,
    ) {
        $this->id = (string) new Ulid();
        $this->roles = [$role];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_READER']));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hash): void
    {
        $this->password = $hash;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $this->enabled && $user->enabled
            && $this->email === $user->email && $this->password === $user->password
            && $this->roles === $user->roles;
    }
}
