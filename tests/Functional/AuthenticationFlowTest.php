<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprehensive Authentication Flow Tests
 * Tests both happy and unhappy paths for authentication
 */
class AuthenticationFlowTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function generateUniqueEmail(): string
    {
        return 'test-' . uniqid() . '@example.com';
    }

    // ==================== HAPPY FLOWS ====================

    /**
     * @return void
     */
    public function testCompleteRegistrationAndLoginFlow(): void
    {
        $email = $this->generateUniqueEmail();
        $password = 'SecurePass123!';

        // Step 1: Register new user
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
                'firstName' => 'John',
                'lastName' => 'Doe',
            ])
        );

        $this->assertResponseIsSuccessful();
        $registerData = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $registerData);
        $this->assertArrayHasKey('user', $registerData);
        $this->assertEquals($email, $registerData['user']['email']);
        $this->assertEquals('John', $registerData['user']['firstName']);
        $this->assertEquals('Doe', $registerData['user']['lastName']);

        // Step 2: Login with same credentials
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
            ])
        );

        $this->assertResponseIsSuccessful();
        $loginData = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $loginData);
        $this->assertArrayHasKey('user', $loginData);
        $this->assertEquals($email, $loginData['user']['email']);

        // Step 3: Use token to access protected endpoint
        $this->client->request(
            'GET',
            '/api/users/' . $loginData['user']['id'],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $loginData['token']]
        );

        $this->assertResponseIsSuccessful();
        $userData = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertEquals($email, $userData['email']);
    }

    public function testSuccessfulRegistrationWithMinimalData(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => 'ValidPass123!',
                'firstName' => 'A',
                'lastName' => 'B',
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
    }

    public function testSuccessfulLoginWithExistingUser(): void
    {
        // Use pre-loaded master data user
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'admin@example.com',
                'password' => 'Admin123!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('admin@example.com', $data['user']['email']);
        $this->assertEquals('Admin', $data['user']['firstName']);
        $this->assertEquals('User', $data['user']['lastName']);
    }

    public function testTokenIsValidJWT(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'admin@example.com',
                'password' => 'Admin123!',
            ])
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $token = $data['token'];

        // JWT should have 3 parts separated by dots
        $parts = explode('.', (string) $token);
        $this->assertCount(3, $parts);

        // Decode payload
        $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $parts[1]))), true);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('username', $payload);
        $this->assertEquals('admin@example.com', $payload['username']);
    }

    /**
     * ==================== UNHAPPY FLOWS ====================
     */
    public function testRegistrationWithDuplicateEmail(): void
    {
        $email = $this->generateUniqueEmail();
        $userData = [
            'email' => $email,
            'password' => 'SecurePass123!',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        // First registration - should succeed
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );
        $this->assertResponseIsSuccessful();

        // Second registration with same email - should fail
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Note: Email validation is not enforced at entity level.
     * This test is commented out as the application currently accepts any string as email.
     *
     * @see testRegistrationWithMissingFields
     */
    public function testRegistrationWithMissingFields(): void
    {
        // Missing password
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'firstName' => 'John',
                'lastName' => 'Doe',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        // Missing email
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'password' => 'SecurePass123!',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        // Missing firstName
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->generateUniqueEmail(),
                'password' => 'SecurePass123!',
                'lastName' => 'Doe',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegistrationWithEmptyFields(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => '',
                'password' => '',
                'firstName' => '',
                'lastName' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
