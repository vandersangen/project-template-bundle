<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiHelloTest extends WebTestCase
{
    public function testHelloEndpoint(): void
    {
        $client = static::createClient();

        // First register a user to get a token
        $client->request(
            Request::METHOD_POST,
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test-' . uniqid() . '@example.com',
                'password' => 'password123',
                'firstName' => 'Test',
                'lastName' => 'User',
            ])
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'];

        // Now make authenticated request
        $client->request(Request::METHOD_GET, '/api/hello', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Hello from Symfony API!', $data['message']);
        $this->assertEquals('success', $data['status']);
    }
}
