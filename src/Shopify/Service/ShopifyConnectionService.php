<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\CredentialEncryptor;
use VanDerSangen\ProjectTemplateBundle\Shopify\Client\ShopifyApiClient;
use VanDerSangen\ProjectTemplateBundle\Shopify\Entity\ShopifyConnection;
use VanDerSangen\ProjectTemplateBundle\Shopify\Enum\ShopifyConnectionStatus;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;
use VanDerSangen\ProjectTemplateBundle\Shopify\Repository\ShopifyConnectionRepository;

/**
 * Connects a tenant's Shopify store using credentials from a merchant-created
 * custom app (Admin API access token), verifies them against the Admin API
 * and stores them encrypted.
 */
class ShopifyConnectionService
{
    public function __construct(
        private readonly ShopifyConnectionRepository $connectionRepository,
        private readonly ShopifyApiClient $apiClient,
        private readonly CredentialEncryptor $encryptor,
    ) {
    }

    /**
     * Normalizes user input to a full myshopify.com domain.
     *
     * Accepts "my-store", "my-store.myshopify.com" or "https://my-store.myshopify.com/".
     *
     * @throws InvalidArgumentException When the input is not a valid shop domain
     */
    public static function normalizeShopDomain(string $input): string
    {
        $domain = strtolower(trim($input));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = explode('/', $domain)[0];

        if ($domain !== '' && !str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $domain)) {
            throw new InvalidArgumentException(
                'Invalid shop domain. Use your myshopify.com domain, e.g. "my-store.myshopify.com".'
            );
        }

        return $domain;
    }

    /**
     * Verifies the credentials against the Shopify Admin API and stores
     * (or updates) the tenant's connection with the token encrypted at rest.
     *
     * @throws InvalidArgumentException When the shop domain or token is invalid input
     * @throws ShopifyApiException When Shopify rejects the credentials or is unreachable
     */
    public function connect(
        int $tenantId,
        string $shopDomain,
        #[SensitiveParameter] string $accessToken,
        ?string $apiKey = null,
        #[SensitiveParameter] ?string $apiSecret = null,
    ): ShopifyConnection {
        $shopDomain = self::normalizeShopDomain($shopDomain);

        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new InvalidArgumentException('Access token is required');
        }

        // Verify the credentials before persisting anything
        $shop = $this->apiClient->getShop($shopDomain, $accessToken);

        $connection = $this->connectionRepository->findByTenantId($tenantId) ?? new ShopifyConnection();
        $isNew = $connection->getId() === null;

        $connection
            ->setTenantId($tenantId)
            ->setShopDomain($shopDomain)
            ->setAccessToken($this->encryptor->encrypt($accessToken))
            ->setApiKey($apiKey !== null && trim($apiKey) !== '' ? trim($apiKey) : null)
            ->setApiSecret(
                $apiSecret !== null && trim($apiSecret) !== '' ? $this->encryptor->encrypt(trim($apiSecret)) : null
            )
            ->setShopName(isset($shop['name']) ? (string) $shop['name'] : null)
            ->setShopId(isset($shop['id']) ? (string) $shop['id'] : null)
            ->setStatus(ShopifyConnectionStatus::CONNECTED)
            ->setLastError(null)
            ->setLastVerifiedAt(new DateTimeImmutable());

        if (!$isNew) {
            $connection->setUpdatedAt(new DateTimeImmutable());
        }

        $this->connectionRepository->save($connection, true);

        return $connection;
    }

    public function getForTenant(int $tenantId): ?ShopifyConnection
    {
        return $this->connectionRepository->findByTenantId($tenantId);
    }

    /**
     * Re-verifies the stored credentials and updates the connection status.
     */
    public function verify(ShopifyConnection $connection): ShopifyConnection
    {
        try {
            $shop = $this->apiClient->getShop(
                (string) $connection->getShopDomain(),
                $this->getDecryptedAccessToken($connection)
            );

            $connection
                ->setShopName(isset($shop['name']) ? (string) $shop['name'] : $connection->getShopName())
                ->setShopId(isset($shop['id']) ? (string) $shop['id'] : $connection->getShopId())
                ->setStatus(ShopifyConnectionStatus::CONNECTED)
                ->setLastError(null)
                ->setLastVerifiedAt(new DateTimeImmutable());
        } catch (ShopifyApiException $exception) {
            $connection
                ->setStatus(ShopifyConnectionStatus::ERROR)
                ->setLastError($exception->getMessage());
        }

        $connection->setUpdatedAt(new DateTimeImmutable());
        $this->connectionRepository->save($connection, true);

        return $connection;
    }

    public function disconnect(ShopifyConnection $connection): void
    {
        $this->connectionRepository->remove($connection, true);
    }

    /**
     * Decrypted Admin API access token, for services that call the Shopify API.
     */
    public function getDecryptedAccessToken(ShopifyConnection $connection): string
    {
        return $this->encryptor->decrypt((string) $connection->getAccessToken());
    }

    /**
     * Decrypted custom app API secret key (for webhook HMAC validation), if configured.
     */
    public function getDecryptedApiSecret(ShopifyConnection $connection): ?string
    {
        $apiSecret = $connection->getApiSecret();

        return $apiSecret === null ? null : $this->encryptor->decrypt($apiSecret);
    }
}
