<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\Shopify;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use VanDerSangen\ProjectTemplateBundle\Shopify\Client\ShopifyApiClient;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;

class ShopifyConnectionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep the same container across requests so the mocked ShopifyApiClient
        // (set via getContainer()->set()) survives; a kernel reboot would discard
        // it and make the client hit the real Shopify API.
        $this->client->disableReboot();
    }

    public function testConnectFlowWithValidCredentials(): void
    {
        $token = $this->registerUserWithTenant();
        $this->mockShopifyApi(['id' => 12345, 'name' => 'Test Store']);

        // Connect the shop
        $this->jsonRequest('POST', '/api/shopify/connection', $token, [
            'shopDomain' => 'test-store',
            'accessToken' => 'shpat_valid_token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();
        $this->assertSame('test-store.myshopify.com', $data['shopDomain']);
        $this->assertSame('Test Store', $data['shopName']);
        $this->assertSame('connected', $data['status']);
        $this->assertArrayNotHasKey('accessToken', $data);

        // Fetch the connection
        $this->jsonRequest('GET', '/api/shopify/connection', $token);
        $this->assertResponseIsSuccessful();
        $this->assertSame('test-store.myshopify.com', $this->responseData()['shopDomain']);

        // Disconnect
        $this->jsonRequest('DELETE', '/api/shopify/connection', $token);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->jsonRequest('GET', '/api/shopify/connection', $token);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testConnectWithInvalidTokenIsRejectedAndNotStored(): void
    {
        $token = $this->registerUserWithTenant();

        $mock = $this->createMock(ShopifyApiClient::class);
        $mock->method('getShop')
            ->willThrowException(new ShopifyApiException('Invalid Shopify Admin API access token', 401));
        static::getContainer()->set(ShopifyApiClient::class, $mock);

        $this->jsonRequest('POST', '/api/shopify/connection', $token, [
            'shopDomain' => 'test-store',
            'accessToken' => 'shpat_invalid',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStringContainsString('Invalid Shopify', $this->responseData()['error']);

        $this->jsonRequest('GET', '/api/shopify/connection', $token);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testConnectRequiresShopDomainAndAccessToken(): void
    {
        $token = $this->registerUserWithTenant();

        $this->jsonRequest('POST', '/api/shopify/connection', $token, ['shopDomain' => 'test-store']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testConnectRejectsInvalidShopDomain(): void
    {
        $token = $this->registerUserWithTenant();

        $this->jsonRequest('POST', '/api/shopify/connection', $token, [
            'shopDomain' => 'https://example.com',
            'accessToken' => 'shpat_valid_token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertStringContainsString('Invalid shop domain', $this->responseData()['error']);
    }

    public function testEndpointsRequireAuthentication(): void
    {
        $this->client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/shopify/connection');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function registerUserWithTenant(): string
    {
        $email = 'shopify-test-' . uniqid() . '@example.com';

        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'SecurePass123!',
                'firstName' => 'Shopify',
                'lastName' => 'Tester',
            ])
        );
        $this->assertResponseIsSuccessful();
        $token = $this->responseData()['token'];

        $this->jsonRequest('POST', '/api/tenants', $token, ['name' => 'Shopify Test Tenant']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $token;
    }

    private function mockShopifyApi(array $shop): void
    {
        $mock = $this->createMock(ShopifyApiClient::class);
        $mock->method('getShop')->willReturn($shop);
        static::getContainer()->set(ShopifyApiClient::class, $mock);
    }

    private function jsonRequest(string $method, string $uri, string $token, array $body = []): void
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $body === [] ? null : json_encode($body)
        );
    }

    private function responseData(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }
}
