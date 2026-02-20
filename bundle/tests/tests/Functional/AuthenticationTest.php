<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationTest extends WebTestCase
{
    private $client;
    private static $testUserPassword = 'SecurePassword123!';

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function generateUniqueEmail(): string
    {
        return 'test-' . uniqid() . '@example.com';
    }

    public function testRegisterNewUser(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => self::$testUserPassword,
                'firstName' => 'Test',
                'lastName' => 'User',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertEquals('Test', $data['user']['firstName']);
        $this->assertEquals('User', $data['user']['lastName']);

        // Verify token is a valid JWT (has 3 parts separated by dots)
        $this->assertCount(3, explode('.', (string) $data['token']));
    }

    public function testRegisterDuplicateEmail(): void
    {
        $email = $this->generateUniqueEmail();

        // First registration
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123',
                'firstName' => 'First',
                'lastName' => 'User',
            ])
        );

        $this->assertResponseIsSuccessful();

        // Try to register again with same email
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password456',
                'firstName' => 'Second',
                'lastName' => 'User',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testLoginWithValidCredentials(): void
    {
        $email = $this->generateUniqueEmail();

        // First register a user
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123',
                'firstName' => 'Login',
                'lastName' => 'Test',
            ])
        );

        // Now login
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123',
            ])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($email, $data['user']['email']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testAuthenticatedRequestWithValidToken(): void
    {
        // Register and get token
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => 'password123',
                'firstName' => 'Auth',
                'lastName' => 'Test',
            ])
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $token = $data['token'];

        // Make authenticated request
        $this->client->request('GET', '/api/hello', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        // Check for refreshed token in response header
        $this->assertTrue($this->client->getResponse()->headers->has('X-Auth-Token'));
        $refreshedToken = $this->client->getResponse()->headers->get('X-Auth-Token');
        $this->assertNotEmpty($refreshedToken);
        $this->assertCount(3, explode('.', (string) $refreshedToken));
    }

    public function testAuthenticatedRequestWithoutToken(): void
    {
        // Make request without token
        $this->client->request('GET', '/api/hello');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Authentication required', $data['error']);
    }

    public function testAuthenticatedRequestWithInvalidToken(): void
    {
        // Make request with invalid token
        $this->client->request(
            'GET',
            '/api/hello',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid.token.here']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTokenRefreshUpdatesTimestamp(): void
    {
        // Register and get token
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => 'password123',
                'firstName' => 'Refresh',
                'lastName' => 'Test',
            ])
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $originalToken = $data['token'];

        // Decode original token to get iat
        $tokenParts = explode('.', (string) $originalToken);
        $originalPayload = json_decode(
            base64_decode(
                str_replace(
                    '_',
                    '/',
                    str_replace('-', '+', $tokenParts[1])
                )
            ),
            true
        );
        $originalIat = $originalPayload['iat'];

        // Wait 2 seconds
        sleep(2);

        // Make authenticated request
        $this->client->request('GET', '/api/hello', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $originalToken,
        ]);

        $this->assertResponseIsSuccessful();

        // Get refreshed token
        $refreshedToken = $this->client->getResponse()->headers->get('X-Auth-Token');
        $this->assertNotNull($refreshedToken, 'X-Auth-Token header should be present');

        $refreshedTokenParts = explode('.', $refreshedToken);
        $refreshedPayload = json_decode(
            base64_decode(
                str_replace(
                    '_',
                    '/',
                    str_replace(
                        '-',
                        '+',
                        $refreshedTokenParts[1]
                    )
                )
            ),
            true
        );
        $refreshedIat = $refreshedPayload['iat'];

        // Verify iat was updated
        $this->assertGreaterThan($originalIat, $refreshedIat);
    }

    public function testPublicEndpointsDoNotRequireAuthentication(): void
    {
        // Register endpoint should be accessible without token
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => 'password123',
                'firstName' => 'Public',
                'lastName' => 'Test',
            ])
        );

        // Should be successful (200) not 401 Unauthorized
        $this->assertResponseIsSuccessful();
    }
}
