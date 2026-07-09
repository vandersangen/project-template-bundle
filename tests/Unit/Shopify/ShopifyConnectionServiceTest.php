<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Shopify;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;
use VanDerSangen\ProjectTemplateBundle\Shopify\Client\ShopifyApiClient;
use VanDerSangen\ProjectTemplateBundle\Shopify\Entity\ShopifyConnection;
use VanDerSangen\ProjectTemplateBundle\Shopify\Enum\ShopifyConnectionStatus;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;
use VanDerSangen\ProjectTemplateBundle\Shopify\Repository\ShopifyConnectionRepository;
use VanDerSangen\ProjectTemplateBundle\Shopify\Service\ShopifyConnectionService;

class ShopifyConnectionServiceTest extends TestCase
{
    public function testNormalizeShopDomainVariants(): void
    {
        $this->assertSame(
            'my-store.myshopify.com',
            ShopifyConnectionService::normalizeShopDomain('my-store')
        );
        $this->assertSame(
            'my-store.myshopify.com',
            ShopifyConnectionService::normalizeShopDomain('my-store.myshopify.com')
        );
        $this->assertSame(
            'my-store.myshopify.com',
            ShopifyConnectionService::normalizeShopDomain('https://My-Store.myshopify.com/admin')
        );
    }

    public function testNormalizeShopDomainRejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ShopifyConnectionService::normalizeShopDomain('example.com');
    }

    public function testConnectVerifiesCredentialsAndStoresEncryptedToken(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $apiClient = $this->createMock(ShopifyApiClient::class);
        $apiClient->expects($this->once())
            ->method('getShop')
            ->with('my-store.myshopify.com', 'shpat_token')
            ->willReturn(['id' => 12345, 'name' => 'My Store']);

        $repository = $this->createMock(ShopifyConnectionRepository::class);
        $repository->method('findByTenantId')->willReturn(null);
        $repository->expects($this->once())->method('save');

        $service = new ShopifyConnectionService($repository, $apiClient, $encryptor);
        $connection = $service->connect(42, 'my-store', 'shpat_token');

        $this->assertSame(42, $connection->getTenantId());
        $this->assertSame('my-store.myshopify.com', $connection->getShopDomain());
        $this->assertSame('My Store', $connection->getShopName());
        $this->assertSame('12345', $connection->getShopId());
        $this->assertSame(ShopifyConnectionStatus::CONNECTED, $connection->getStatus());
        $this->assertNotNull($connection->getLastVerifiedAt());

        // Token is stored encrypted, and can be decrypted back
        $this->assertNotSame('shpat_token', $connection->getAccessToken());
        $this->assertSame('shpat_token', $service->getDecryptedAccessToken($connection));
    }

    public function testConnectDoesNotPersistWhenCredentialsAreRejected(): void
    {
        $apiClient = $this->createMock(ShopifyApiClient::class);
        $apiClient->method('getShop')
            ->willThrowException(new ShopifyApiException('Invalid Shopify Admin API access token', 401));

        $repository = $this->createMock(ShopifyConnectionRepository::class);
        $repository->method('findByTenantId')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $service = new ShopifyConnectionService($repository, $apiClient, new CredentialEncryptor('test-secret'));

        $this->expectException(ShopifyApiException::class);
        $service->connect(42, 'my-store', 'invalid-token');
    }

    public function testVerifyMarksConnectionAsErrorWhenTokenRevoked(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $connection = new ShopifyConnection()
            ->setTenantId(42)
            ->setShopDomain('my-store.myshopify.com')
            ->setAccessToken($encryptor->encrypt('shpat_token'));

        $apiClient = $this->createMock(ShopifyApiClient::class);
        $apiClient->method('getShop')
            ->willThrowException(new ShopifyApiException('Invalid Shopify Admin API access token', 401));

        $repository = $this->createMock(ShopifyConnectionRepository::class);
        $repository->expects($this->once())->method('save')->with($connection, true);

        $service = new ShopifyConnectionService($repository, $apiClient, $encryptor);
        $connection = $service->verify($connection);

        $this->assertSame(ShopifyConnectionStatus::ERROR, $connection->getStatus());
        $this->assertStringContainsString(
            'Invalid Shopify Admin API access token',
            (string) $connection->getLastError()
        );
    }

    public function testApiSecretIsStoredEncrypted(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $apiClient = $this->createMock(ShopifyApiClient::class);
        $apiClient->method('getShop')->willReturn(['id' => 1, 'name' => 'My Store']);

        $repository = $this->createMock(ShopifyConnectionRepository::class);
        $repository->method('findByTenantId')->willReturn(null);

        $service = new ShopifyConnectionService($repository, $apiClient, $encryptor);
        $connection = $service->connect(42, 'my-store', 'shpat_token', 'api-key-123', 'shpss_secret');

        $this->assertSame('api-key-123', $connection->getApiKey());
        $this->assertNotSame('shpss_secret', $connection->getApiSecret());
        $this->assertSame('shpss_secret', $service->getDecryptedApiSecret($connection));
    }

    public function testToArrayNeverExposesSecrets(): void
    {
        $encryptor = new CredentialEncryptor('test-secret');

        $apiClient = $this->createMock(ShopifyApiClient::class);
        $apiClient->method('getShop')->willReturn(['id' => 1, 'name' => 'My Store']);

        $repository = $this->createMock(ShopifyConnectionRepository::class);
        $repository->method('findByTenantId')->willReturn(null);

        $service = new ShopifyConnectionService($repository, $apiClient, $encryptor);
        $connection = $service->connect(42, 'my-store', 'shpat_token', null, 'shpss_secret');

        $array = $connection->toArray();

        $this->assertArrayNotHasKey('accessToken', $array);
        $this->assertArrayNotHasKey('apiSecret', $array);
        $this->assertTrue($array['hasApiSecret']);
        $this->assertStringNotContainsString('shpat_token', json_encode($array));
        $this->assertStringNotContainsString('shpss_secret', json_encode($array));
    }
}
