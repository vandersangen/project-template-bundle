<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Service;

use SensitiveParameter;
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
        private readonly TotpService $totpService,
        private readonly TwoFactorChallengeService $challengeService,
    ) {
    }

    /**
     * Authenticate with e-mail + password.
     *
     * Returns a full {token, user} payload when 2FA is off, or a
     * {twoFactorRequired: true, challenge} payload when the account has 2FA
     * enabled — in which case the caller must complete {@see verifyTwoFactor}.
     * Returns null on invalid credentials.
     */
    public function login(string $email, #[SensitiveParameter] string $password): ?array
    {
        $user = $this->userService->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user->getPassword())) {
            return null;
        }

        if ($user->isTotpEnabled()) {
            return [
                'twoFactorRequired' => true,
                'challenge' => $this->challengeService->mint((int) $user->getId()),
            ];
        }

        return $this->issueToken($user);
    }

    /**
     * Complete a 2FA login: given the challenge from {@see login} and a TOTP
     * code (or a one-time backup code), return a full {token, user} payload, or
     * null if the challenge or code is invalid.
     */
    public function verifyTwoFactor(string $challenge, #[SensitiveParameter] string $code): ?array
    {
        $userId = $this->challengeService->resolveUserId($challenge);
        if ($userId === null) {
            return null;
        }

        $user = $this->userService->findById($userId);
        if (!$user || !$user->isTotpEnabled() || $user->getTotpSecret() === null) {
            return null;
        }

        $secret = $this->totpService->decryptSecret($user->getTotpSecret());
        if ($this->totpService->verifyCode($secret, $code)) {
            return $this->issueToken($user);
        }

        // Fall back to a one-time recovery code.
        $remaining = $this->totpService->consumeBackupCode($user->getTotpBackupCodes() ?? [], $code);
        if ($remaining !== null) {
            $user->setTotpBackupCodes($remaining);
            $this->userService->save($user);

            return $this->issueToken($user);
        }

        return null;
    }

    /**
     * @return array{token: string, user: array}
     */
    private function issueToken(User $user): array
    {
        return [
            'token' => $this->jwtManager->create($user),
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
