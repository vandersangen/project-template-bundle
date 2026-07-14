<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional;

use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional coverage of the opt-in TOTP two-factor flow against the bundle's
 * own test kernel: register -> setup -> enable -> login (challenge) -> verify,
 * plus the backup-code path, the firewall rules and disable.
 */
class TwoFactorFlowTest extends WebTestCase
{
    private const string PASSWORD = 'SecurePassword123!';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testFullEnableLoginVerifyFlow(): void
    {
        $enrolled = $this->enroll();

        // Status reflects enabled.
        $status = $this->json('GET', '/api/profile/2fa/status', token: $enrolled['token']);
        self::assertTrue($status['enabled']);
        self::assertCount(8, $enrolled['backupCodes']);

        // Login now demands a second factor instead of handing out a token.
        $login = $this->json('POST', '/api/auth/login', body: [
            'email' => $enrolled['email'],
            'password' => self::PASSWORD,
        ]);
        self::assertTrue($login['twoFactorRequired']);
        self::assertArrayNotHasKey('token', $login);

        // Verifying with a fresh TOTP code yields the real token.
        $verify = $this->json('POST', '/api/auth/2fa/verify', body: [
            'challenge' => $login['challenge'],
            'code' => TOTP::createFromSecret($enrolled['secret'])->now(),
        ]);
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $verify);
    }

    public function testSetupDoesNotEnableUntilConfirmed(): void
    {
        $token = $this->register()['token'];

        $setup = $this->json('POST', '/api/profile/2fa/setup', token: $token);
        self::assertStringStartsWith('otpauth://totp/', $setup['otpauthUri']);

        $status = $this->json('GET', '/api/profile/2fa/status', token: $token);
        self::assertFalse($status['enabled']);
    }

    public function testBackupCodeVerifiesOnceThenIsRejected(): void
    {
        $enrolled = $this->enroll();
        $backup = $enrolled['backupCodes'][0];

        $first = $this->json('POST', '/api/auth/2fa/verify', body: [
            'challenge' => $this->challengeFor($enrolled['email']),
            'code' => $backup,
        ]);
        self::assertArrayHasKey('token', $first);

        $this->json('POST', '/api/auth/2fa/verify', body: [
            'challenge' => $this->challengeFor($enrolled['email']),
            'code' => $backup,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testVerifyEndpointIsPublicButRejectsBogusChallenge(): void
    {
        $this->json('POST', '/api/auth/2fa/verify', body: [
            'challenge' => 'bogus',
            'code' => '123456',
        ]);

        // 401 means it reached the controller (not blocked by the firewall).
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testEnrolmentEndpointsRequireAuthentication(): void
    {
        $this->client->request('POST', '/api/profile/2fa/setup');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDisableWithValidCodeRestoresNormalLogin(): void
    {
        $enrolled = $this->enroll();

        $this->json('POST', '/api/profile/2fa/disable', body: [
            'code' => TOTP::createFromSecret($enrolled['secret'])->now(),
        ], token: $enrolled['token']);
        self::assertResponseIsSuccessful();

        $login = $this->json('POST', '/api/auth/login', body: [
            'email' => $enrolled['email'],
            'password' => self::PASSWORD,
        ]);
        self::assertArrayHasKey('token', $login);
        self::assertArrayNotHasKey('twoFactorRequired', $login);
    }

    // ---- helpers -----------------------------------------------------------

    /**
     * @return array{email: string, token: string}
     */
    private function register(): array
    {
        $email = 'twofactor-' . uniqid() . '@example.com';

        $data = $this->json('POST', '/api/auth/register', body: [
            'email' => $email,
            'password' => self::PASSWORD,
            'firstName' => 'Two',
            'lastName' => 'Factor',
        ]);

        return ['email' => $email, 'token' => $data['token']];
    }

    /**
     * @return array{email: string, token: string, secret: string, backupCodes: list<string>}
     */
    private function enroll(): array
    {
        ['email' => $email, 'token' => $token] = $this->register();

        $secret = $this->json('POST', '/api/profile/2fa/setup', token: $token)['secret'];

        $enable = $this->json('POST', '/api/profile/2fa/enable', body: [
            'code' => TOTP::createFromSecret($secret)->now(),
        ], token: $token);

        return [
            'email' => $email,
            'token' => $token,
            'secret' => $secret,
            'backupCodes' => $enable['backupCodes'],
        ];
    }

    private function challengeFor(string $email): string
    {
        return $this->json('POST', '/api/auth/login', body: [
            'email' => $email,
            'password' => self::PASSWORD,
        ])['challenge'];
    }

    /**
     * @param string                     $method
     * @param string                     $path
     * @param array<string, mixed>|null  $body
     * @param string|null                $token
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null, ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request($method, $path, [], [], $server, $body !== null ? (string) json_encode($body) : null);

        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
