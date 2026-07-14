<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Auth;

use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Auth\Service\TotpService;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;

class TotpServiceTest extends TestCase
{
    private function service(): TotpService
    {
        return new TotpService(new CredentialEncryptor('test-secret'));
    }

    public function testGenerateSecretIsNonEmptyBase32(): void
    {
        $secret = $this->service()->generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testProvisioningUriContainsIssuerAndAccount(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();

        $uri = $service->buildProvisioningUri($secret, 'user@qonnecthub.nl', 'Qonnecthub');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('Qonnecthub', $uri);
        $this->assertStringContainsString(rawurlencode('user@qonnecthub.nl'), $uri);
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();

        $code = TOTP::createFromSecret($secret)->now();

        $this->assertTrue($service->verifyCode($secret, $code));
    }

    public function testVerifyToleratesAdjacentWindowDrift(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();
        $totp = TOTP::createFromSecret($secret);

        // A code from the previous period must still validate (slow typing / drift).
        $previousCode = $totp->at(time() - $totp->getPeriod());

        $this->assertTrue($service->verifyCode($secret, $previousCode));
    }

    public function testVerifyRejectsWrongCode(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();

        $this->assertFalse($service->verifyCode($secret, '000000'));
    }

    public function testVerifyIgnoresSurroundingWhitespace(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();
        $code = TOTP::createFromSecret($secret)->now();

        $this->assertTrue($service->verifyCode($secret, ' ' . substr($code, 0, 3) . ' ' . substr($code, 3) . ' '));
    }

    public function testGenerateBackupCodesReturnsFormattedPlainAndHashes(): void
    {
        $codes = $this->service()->generateBackupCodes();

        $this->assertCount(8, $codes['plain']);
        $this->assertCount(8, $codes['hashed']);

        foreach ($codes['plain'] as $plain) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{5}-[0-9a-f]{5}$/', $plain);
        }
        foreach ($codes['hashed'] as $index => $hash) {
            $this->assertNotSame($codes['plain'][$index], $hash);
            $this->assertStringStartsWith('$', $hash);
        }
    }

    public function testConsumeBackupCodeRemovesMatchedHash(): void
    {
        $service = $this->service();
        $codes = $service->generateBackupCodes();

        $remaining = $service->consumeBackupCode($codes['hashed'], $codes['plain'][2]);

        $this->assertIsArray($remaining);
        $this->assertCount(7, $remaining);
    }

    public function testConsumeBackupCodeAcceptsCodeWithoutDash(): void
    {
        $service = $this->service();
        $codes = $service->generateBackupCodes();

        $noDash = str_replace('-', '', $codes['plain'][0]);

        $this->assertNotNull($service->consumeBackupCode($codes['hashed'], $noDash));
    }

    public function testConsumeBackupCodeReturnsNullForUnknownCode(): void
    {
        $service = $this->service();
        $codes = $service->generateBackupCodes();

        $this->assertNull($service->consumeBackupCode($codes['hashed'], 'zzzzz-zzzzz'));
    }

    public function testSecretEncryptDecryptRoundtrip(): void
    {
        $service = $this->service();
        $secret = $service->generateSecret();

        $ciphertext = $service->encryptSecret($secret);

        $this->assertNotSame($secret, $ciphertext);
        $this->assertSame($secret, $service->decryptSecret($ciphertext));
    }
}
