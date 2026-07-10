<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Shopify;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;

class CredentialEncryptorTest extends TestCase
{
    public function testEncryptDecryptRoundtrip(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $ciphertext = $encryptor->encrypt('shpat_abc123');

        $this->assertNotSame('shpat_abc123', $ciphertext);
        $this->assertStringNotContainsString('shpat_abc123', $ciphertext);
        $this->assertSame('shpat_abc123', $encryptor->decrypt($ciphertext));
    }

    public function testEncryptProducesDifferentCiphertextsForSamePlaintext(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $this->assertNotSame($encryptor->encrypt('token'), $encryptor->encrypt('token'));
    }

    public function testDecryptWithWrongSecretFails(): void
    {
        $ciphertext = new CredentialEncryptor('secret-a')->encrypt('token');

        $this->expectException(RuntimeException::class);
        new CredentialEncryptor('secret-b')->decrypt($ciphertext);
    }

    public function testDecryptInvalidPayloadFails(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $this->expectException(RuntimeException::class);
        $encryptor->decrypt('not-valid-base64!!!');
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CredentialEncryptor('');
    }
}
