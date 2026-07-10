<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Service;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

/**
 * Encrypts third-party credentials (API tokens, secrets) for storage at rest.
 *
 * Uses libsodium XSalsa20-Poly1305 (crypto_secretbox) with a key derived from
 * the configured secret (project_template.encryption_secret, defaults to APP_SECRET).
 * Output format: base64(nonce || ciphertext).
 */
class CredentialEncryptor
{
    private readonly string $key;

    public function __construct(#[SensitiveParameter] string $secret)
    {
        if ($secret === '') {
            throw new InvalidArgumentException(
                'CredentialEncryptor requires a non-empty secret (project_template.encryption_secret / APP_SECRET)'
            );
        }

        $this->key = sodium_crypto_generichash($secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted credential payload');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->key
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt credential (wrong key or corrupted data)');
        }

        return $plaintext;
    }
}
