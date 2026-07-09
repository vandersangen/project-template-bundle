<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Shopify;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VanDerSangen\ProjectTemplateBundle\Shopify\Client\ShopifyApiClient;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;

class ShopifyApiClientTest extends TestCase
{
    public function testGetShopSendsAccessTokenHeaderAndReturnsShop(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://my-store.myshopify.com/admin/api/2026-01/shop.json', $url);
            $this->assertContains('X-Shopify-Access-Token: shpat_token', $options['headers']);

            return new MockResponse(json_encode([
                'shop' => ['id' => 12345, 'name' => 'My Store'],
            ]));
        });

        $client = new ShopifyApiClient($httpClient, '2026-01');
        $shop = $client->getShop('my-store.myshopify.com', 'shpat_token');

        $this->assertSame(12345, $shop['id']);
        $this->assertSame('My Store', $shop['name']);
    }

    public function testInvalidTokenThrowsAuthenticationError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            json_encode(['errors' => '[API] Invalid API key or access token (unrecognized login or wrong password)']),
            ['http_code' => 401]
        ));

        $client = new ShopifyApiClient($httpClient, '2026-01');

        try {
            $client->getShop('my-store.myshopify.com', 'invalid');
            $this->fail('Expected ShopifyApiException');
        } catch (ShopifyApiException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertTrue($exception->isAuthenticationError());
            $this->assertStringContainsString('Invalid API key or access token', $exception->getMessage());
        }
    }

    public function testUnreachableShopThrowsException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host']));

        $client = new ShopifyApiClient($httpClient, '2026-01');

        $this->expectException(ShopifyApiException::class);
        $this->expectExceptionMessageMatches('/Could not reach Shopify/');
        $client->getShop('nonexistent.myshopify.com', 'shpat_token');
    }

    public function testGraphqlPostsQuery(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://my-store.myshopify.com/admin/api/2026-01/graphql.json', $url);
            $body = json_decode((string) $options['body'], true);
            $this->assertSame('{ shop { name } }', $body['query']);

            return new MockResponse(json_encode(['data' => ['shop' => ['name' => 'My Store']]]));
        });

        $client = new ShopifyApiClient($httpClient, '2026-01');
        $result = $client->graphql('my-store.myshopify.com', 'shpat_token', '{ shop { name } }');

        $this->assertSame('My Store', $result['data']['shop']['name']);
    }
}
