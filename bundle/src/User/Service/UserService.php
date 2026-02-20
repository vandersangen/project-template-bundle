<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\User\Service;

use LarsVanDerSangen\ProjectTemplateBundle\User\Entity\User;
use LarsVanDerSangen\ProjectTemplateBundle\User\Repository\UserRepository;
use DateTimeImmutable;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function createUser(string $email, string $password, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        $this->userRepository->save($user, true);

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    public function findById(int $userId): ?User
    {
        return $this->userRepository->find($userId);
    }

    public function generateResetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new DateTimeImmutable('+1 hour'));
        $this->userRepository->save($user, true);

        return $token;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->userRepository->findByResetToken($token);

        if (!$user || $user->getResetTokenExpiresAt() < new DateTimeImmutable()) {
            return false;
        }

        $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->userRepository->save($user, true);

        return true;
    }
}
