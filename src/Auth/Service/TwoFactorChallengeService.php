<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Service;

use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;

/**
 * Issues and validates the short-lived "2FA pending" challenge used between the
 * password step and the TOTP step of a stateless login.
 *
 * The challenge is an encrypted (and therefore tamper-proof, thanks to the
 * secretbox MAC) blob carrying only the user id and an expiry. It is NOT a JWT
 * and carries no roles, so it can never be used as an API access token.
 */
class TwoFactorChallengeService
{
    private const int DEFAULT_TTL_SECONDS = 300;

    public function __construct(
        private readonly CredentialEncryptor $encryptor,
    ) {
    }

    public function mint(int $userId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $payload = json_encode([
            'uid' => $userId,
            'exp' => time() + $ttlSeconds,
        ], JSON_THROW_ON_ERROR);

        return $this->encryptor->encrypt($payload);
    }

    /**
     * Resolve the user id from a challenge, or null if it is invalid, tampered
     * with, or expired.
     */
    public function resolveUserId(string $challenge): ?int
    {
        try {
            $payload = json_decode($this->encryptor->decrypt($challenge), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['uid'], $payload['exp'])) {
            return null;
        }

        if (!is_int($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return is_int($payload['uid']) ? $payload['uid'] : null;
    }
}
