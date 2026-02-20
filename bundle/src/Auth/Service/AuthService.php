<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Auth\Service;

use LarsVanDerSangen\ProjectTemplateBundle\User\Entity\User;
use LarsVanDerSangen\ProjectTemplateBundle\User\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JWTTokenManagerInterface $jwtManager
    ) {
    }

    public function login(string $email, string $password): ?array
    {
        $user = $this->userService->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user->getPassword())) {
            return null;
        }

        $token = $this->jwtManager->create($user);

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    public function register(string $email, string $password, string $firstName, string $lastName): ?array
    {
        $existingUser = $this->userService->findByEmail($email);
        if ($existingUser) {
            return null;
        }

        $user = $this->userService->createUser($email, $password, $firstName, $lastName);

        $token = $this->jwtManager->create($user);

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    public function forgotPassword(string $email): ?string
    {
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            return null;
        }

        return $this->userService->generateResetToken($user);
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        return $this->userService->resetPassword($token, $newPassword);
    }
}
