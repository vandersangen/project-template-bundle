<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Service;

use OTPHP\TOTP;
use SensitiveParameter;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;

/**
 * TOTP (RFC 6238) helper for opt-in two-factor authentication.
 *
 * Secrets are handled in plaintext here but are always stored encrypted at
 * rest via {@see CredentialEncryptor} (encryptSecret/decryptSecret). Backup
 * codes are returned in plaintext once (to show the user) and otherwise only
 * ever stored/compared as password hashes.
 */
class TotpService
{
    private const int BACKUP_CODE_COUNT = 8;
    private const int BACKUP_CODE_BYTES = 5; // -> 10 hex chars

    // RFC 6238 recommends a 160-bit (20-byte) secret. This is what the common
    // authenticator apps expect and keeps the enrolment QR comfortably scannable;
    // otphp's default (64 bytes) produces a needlessly dense code.
    private const int SECRET_BYTES = 20;

    public function __construct(
        private readonly CredentialEncryptor $encryptor,
    ) {
    }

    /**
     * Generate a fresh, unformatted base32 TOTP secret.
     */
    public function generateSecret(): string
    {
        return TOTP::generate(secretSize: self::SECRET_BYTES)->getSecret();
    }

    /**
     * Build the otpauth:// provisioning URI the authenticator app scans as a QR.
     */
    public function buildProvisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountName);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    /**
     * Verify a 6-digit code against the secret, tolerating one period (±30s) of
     * clock drift so a code entered right on a boundary still validates.
     */
    public function verifyCode(string $secret, #[SensitiveParameter] string $code): bool
    {
        $code = $this->normalize($code);
        if ($code === '') {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);
        $period = $totp->getPeriod();

        foreach ([0, -$period, $period] as $offset) {
            if ($totp->verify($code, time() + $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a fresh set of one-time recovery codes.
     *
     * @return array{plain: list<string>, hashed: list<string>}
     *         'plain' is shown to the user once; 'hashed' is what gets stored.
     */
    public function generateBackupCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $raw = bin2hex(random_bytes(self::BACKUP_CODE_BYTES));
            $plain[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            $hashed[] = password_hash($raw, PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * Consume a recovery code: if it matches one of the stored hashes, return
     * the remaining hashes (with the used one removed); otherwise return null.
     *
     * @param array<int, string> $hashes
     *
     * @return array<int, string>|null
     */
    public function consumeBackupCode(array $hashes, #[SensitiveParameter] string $code): ?array
    {
        $candidate = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');
        if ($candidate === '') {
            return null;
        }

        foreach ($hashes as $index => $hash) {
            if (password_verify($candidate, $hash)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    public function encryptSecret(#[SensitiveParameter] string $secret): string
    {
        return $this->encryptor->encrypt($secret);
    }

    public function decryptSecret(string $ciphertext): string
    {
        return $this->encryptor->decrypt($ciphertext);
    }

    private function normalize(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }
}
