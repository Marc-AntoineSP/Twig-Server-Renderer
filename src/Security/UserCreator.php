<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(string $email, string $password, string $role = 'ROLE_READER'): User
    {
        $email = mb_strtolower(trim($email));
        $errors = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Enter a valid email address (maximum 180 characters).');
        }
        if (mb_strlen($password) < 12 || strlen($password) > 4096) {
            throw new \InvalidArgumentException('Use a password of at least 12 characters and at most 4096 bytes.');
        }
        if (!in_array($role, ['ROLE_READER', 'ROLE_EDITOR', 'ROLE_ADMIN'], true)) {
            throw new \InvalidArgumentException('Role must be ROLE_READER, ROLE_EDITOR or ROLE_ADMIN.');
        }
        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            throw new \InvalidArgumentException('An account already exists for this email address.');
        }
        $user = new User($email, $role);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new \InvalidArgumentException('An account already exists for this email address.');
        }

        return $user;
    }
}
