<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Auth;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use VanDerSangen\ProjectTemplateBundle\Auth\Service\AuthService;
use VanDerSangen\ProjectTemplateBundle\Auth\Service\TotpService;
use VanDerSangen\ProjectTemplateBundle\Auth\Service\TwoFactorChallengeService;
use VanDerSangen\ProjectTemplateBundle\Auth\Mail\AuthMailSenderInterface;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use VanDerSangen\ProjectTemplateBundle\User\Service\UserService;

class AuthServiceTwoFactorTest extends TestCase
{
    private const string PASSWORD = 'Secret123!';
    private const int USER_ID = 7;

    private TotpService $totpService;
    private TwoFactorChallengeService $challengeService;
    private string $plainSecret;

    protected function setUp(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');
        $this->totpService = new TotpService($encryptor);
        $this->challengeService = new TwoFactorChallengeService($encryptor);
        $this->plainSecret = $this->totpService->generateSecret();
    }

    public function testLoginWithoutTwoFactorReturnsToken(): void
    {
        $user = $this->makeUser(twoFactor: false);
        $auth = $this->authService($this->userService($user), $this->jwtManager('jwt-abc'));

        $result = $auth->login('user@qonnecthub.nl', self::PASSWORD);

        $this->assertSame('jwt-abc', $result['token']);
        $this->assertArrayNotHasKey('twoFactorRequired', $result);
    }

    public function testLoginWithTwoFactorReturnsChallengeAndNoToken(): void
    {
        $user = $this->makeUser(twoFactor: true);
        $auth = $this->authService($this->userService($user), $this->jwtManagerNeverCalled());

        $result = $auth->login('user@qonnecthub.nl', self::PASSWORD);

        $this->assertTrue($result['twoFactorRequired']);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayNotHasKey('token', $result);
        // The challenge must resolve back to this user.
        $this->assertSame(self::USER_ID, $this->challengeService->resolveUserId($result['challenge']));
    }

    public function testLoginWithWrongPasswordReturnsNull(): void
    {
        $user = $this->makeUser(twoFactor: true);
        $auth = $this->authService($this->userService($user), $this->jwtManagerNeverCalled());

        $this->assertNull($auth->login('user@qonnecthub.nl', 'wrong-password'));
    }

    public function testVerifyWithValidTotpCodeReturnsToken(): void
    {
        $user = $this->makeUser(twoFactor: true);
        $userService = $this->userService($user);
        $userService->expects($this->never())->method('save');
        $auth = $this->authService($userService, $this->jwtManager('jwt-ok'));

        $challenge = $this->challengeService->mint(self::USER_ID);
        $code = TOTP::createFromSecret($this->plainSecret)->now();

        $result = $auth->verifyTwoFactor($challenge, $code);

        $this->assertSame('jwt-ok', $result['token']);
    }

    public function testVerifyWithInvalidChallengeReturnsNull(): void
    {
        $auth = $this->authService(
            $this->createMock(UserService::class),
            $this->jwtManagerNeverCalled(),
        );

        $code = TOTP::createFromSecret($this->plainSecret)->now();

        $this->assertNull($auth->verifyTwoFactor('garbage-challenge', $code));
    }

    public function testVerifyWithWrongCodeReturnsNull(): void
    {
        $user = $this->makeUser(twoFactor: true);
        $auth = $this->authService($this->userService($user), $this->jwtManagerNeverCalled());

        $challenge = $this->challengeService->mint(self::USER_ID);

        $this->assertNull($auth->verifyTwoFactor($challenge, '000000'));
    }

    public function testVerifyWithBackupCodeConsumesItAndReturnsToken(): void
    {
        $codes = $this->totpService->generateBackupCodes();
        $user = $this->makeUser(twoFactor: true, backupHashes: $codes['hashed']);

        $userService = $this->userService($user);
        $userService->expects($this->once())
            ->method('save')
            ->with($this->callback(
                static fn (User $saved): bool => count($saved->getTotpBackupCodes()) === 7,
            ));

        $auth = $this->authService($userService, $this->jwtManager('jwt-backup'));
        $challenge = $this->challengeService->mint(self::USER_ID);

        $result = $auth->verifyTwoFactor($challenge, $codes['plain'][0]);

        $this->assertSame('jwt-backup', $result['token']);
    }

    public function testVerifyRejectsUserWithTwoFactorDisabled(): void
    {
        $user = $this->makeUser(twoFactor: false);
        $auth = $this->authService($this->userService($user), $this->jwtManagerNeverCalled());

        $challenge = $this->challengeService->mint(self::USER_ID);
        $code = TOTP::createFromSecret($this->plainSecret)->now();

        $this->assertNull($auth->verifyTwoFactor($challenge, $code));
    }

    /**
     * @param bool                $twoFactor
     * @param array<int, string>  $backupHashes
     */
    private function makeUser(bool $twoFactor, array $backupHashes = []): User
    {
        $user = new User();
        $user->setEmail('user@qonnecthub.nl');
        $user->setPassword(password_hash(self::PASSWORD, PASSWORD_BCRYPT));
        $user->setFirstName('Two');
        $user->setLastName('Factor');

        $idProperty = new ReflectionProperty(User::class, 'id');
        $idProperty->setValue($user, self::USER_ID);

        if ($twoFactor) {
            $user->setTotpEnabled(true);
            $user->setTotpSecret($this->totpService->encryptSecret($this->plainSecret));
            $user->setTotpBackupCodes($backupHashes);
        }

        return $user;
    }

    private function userService(User $user): UserService
    {
        $userService = $this->createMock(UserService::class);
        $userService->method('findByEmail')->willReturn($user);
        $userService->method('findById')->with(self::USER_ID)->willReturn($user);

        return $userService;
    }

    private function jwtManager(string $token): JWTTokenManagerInterface
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('create')->willReturn($token);

        return $jwtManager;
    }

    private function jwtManagerNeverCalled(): JWTTokenManagerInterface
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->never())->method('create');

        return $jwtManager;
    }

    private function authService(UserService $userService, JWTTokenManagerInterface $jwtManager): AuthService
    {
        return new AuthService(
            $userService,
            $jwtManager,
            $this->createMock(AuthMailSenderInterface::class),
            $this->totpService,
            $this->challengeService,
        );
    }
}
