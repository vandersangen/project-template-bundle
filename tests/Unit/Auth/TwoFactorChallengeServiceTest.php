<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Auth\Service\TwoFactorChallengeService;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;

class TwoFactorChallengeServiceTest extends TestCase
{
    private function service(): TwoFactorChallengeService
    {
        return new TwoFactorChallengeService(new CredentialEncryptor('test-secret'));
    }

    public function testMintAndResolveRoundtrip(): void
    {
        $service = $this->service();

        $challenge = $service->mint(42);

        $this->assertSame(42, $service->resolveUserId($challenge));
    }

    public function testChallengeDoesNotLeakUserId(): void
    {
        $challenge = $this->service()->mint(4242);

        $this->assertStringNotContainsString('4242', $challenge);
    }

    public function testTamperedChallengeIsRejected(): void
    {
        $service = $this->service();
        $challenge = $service->mint(42);

        $this->assertNull($service->resolveUserId($challenge . 'x'));
    }

    public function testExpiredChallengeIsRejected(): void
    {
        $service = $this->service();

        $expired = $service->mint(42, -10);

        $this->assertNull($service->resolveUserId($expired));
    }

    public function testGarbageChallengeIsRejected(): void
    {
        $this->assertNull($this->service()->resolveUserId('not-a-real-token'));
    }

    public function testChallengeFromDifferentSecretIsRejected(): void
    {
        $minted = new TwoFactorChallengeService(new CredentialEncryptor('secret-a'))->mint(42);
        $other = new TwoFactorChallengeService(new CredentialEncryptor('secret-b'));

        $this->assertNull($other->resolveUserId($minted));
    }
}
