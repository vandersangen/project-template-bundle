<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Service;

use VanDerSangen\ProjectTemplateBundle\Mail\Service\MailService;
use VanDerSangen\ProjectTemplateBundle\Mail\Template\DefaultMailTemplate;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use VanDerSangen\ProjectTemplateBundle\User\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly MailService $mailService,
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

        $this->sendWelcomeMail($user);

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

        $resetToken = $this->userService->generateResetToken($user);

        $this->sendForgotPasswordMail($user, $resetToken);

        return $resetToken;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->userService->findByResetToken($token);

        $success = $this->userService->resetPassword($token, $newPassword);

        if ($success && $user) {
            $this->sendPasswordResetConfirmationMail($user);
        }

        return $success;
    }

    private function sendWelcomeMail(User $user): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Welcome to our platform',
            DefaultMailTemplate::WELCOME,
            [
                'name' => $user->getFirstName(),
                'email' => (string) $user->getEmail(),
            ],
            [(string) $user->getEmail()],
        );
    }

    private function sendForgotPasswordMail(User $user, string $resetToken): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Password Reset Request',
            DefaultMailTemplate::PASSWORD_RESET,
            [
                'name' => $user->getFirstName(),
                'resetUrl' => $resetToken,
                'expiry' => '1 hour',
            ],
            [(string) $user->getEmail()],
        );
    }

    private function sendPasswordResetConfirmationMail(User $user): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Password Changed Successfully',
            DefaultMailTemplate::PASSWORD_RESET_CONFIRMATION,
            [
                'name' => $user->getFirstName(),
            ],
            [(string) $user->getEmail()],
        );
    }
}
