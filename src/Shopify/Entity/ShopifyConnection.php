<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Shopify\Enum\ShopifyConnectionStatus;
use VanDerSangen\ProjectTemplateBundle\Shopify\Repository\ShopifyConnectionRepository;

/**
 * A tenant's connection to their own Shopify store via a custom app
 * ("Develop apps" in the Shopify admin) using an Admin API access token,
 * instead of OAuth through a public App Store app.
 *
 * The access token (and optional API secret key) are stored encrypted at rest.
 */
#[ORM\Entity(repositoryClass: ShopifyConnectionRepository::class)]
#[ORM\Table(name: 'shopify_connections')]
#[ORM\UniqueConstraint(name: 'uniq_shopify_connection_tenant', columns: ['tenant_id'])]
class ShopifyConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Foreign key to tenants.id */
    #[ORM\Column]
    private ?int $tenantId = null;

    /** Full myshopify.com domain, e.g. "my-store.myshopify.com" */
    #[ORM\Column(length: 255)]
    private ?string $shopDomain = null;

    /** Admin API access token (shpat_...), encrypted at rest */
    #[ORM\Column(type: 'text')]
    private ?string $accessToken = null;

    /** Custom app API key (optional, plain — not a secret) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiKey = null;

    /** Custom app API secret key (optional, used for webhook HMAC validation), encrypted at rest */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiSecret = null;

    /** Shop name as reported by the Shopify Admin API */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shopName = null;

    /** Shopify shop ID as reported by the Admin API */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $shopId = null;

    #[ORM\Column(length: 16, enumType: ShopifyConnectionStatus::class)]
    private ShopifyConnectionStatus $status = ShopifyConnectionStatus::CONNECTED;

    /** Last error message from a failed verification */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastVerifiedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function setTenantId(int $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getShopDomain(): ?string
    {
        return $this->shopDomain;
    }

    public function setShopDomain(string $shopDomain): static
    {
        $this->shopDomain = $shopDomain;
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getApiSecret(): ?string
    {
        return $this->apiSecret;
    }

    public function setApiSecret(?string $apiSecret): static
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    public function getShopName(): ?string
    {
        return $this->shopName;
    }

    public function setShopName(?string $shopName): static
    {
        $this->shopName = $shopName;
        return $this;
    }

    public function getShopId(): ?string
    {
        return $this->shopId;
    }

    public function setShopId(?string $shopId): static
    {
        $this->shopId = $shopId;
        return $this;
    }

    public function getStatus(): ShopifyConnectionStatus
    {
        return $this->status;
    }

    public function setStatus(ShopifyConnectionStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function getLastVerifiedAt(): ?DateTimeImmutable
    {
        return $this->lastVerifiedAt;
    }

    public function setLastVerifiedAt(?DateTimeImmutable $lastVerifiedAt): static
    {
        $this->lastVerifiedAt = $lastVerifiedAt;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * API representation. Never exposes the access token or API secret.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'shopDomain' => $this->shopDomain,
            'shopName' => $this->shopName,
            'shopId' => $this->shopId,
            'apiKey' => $this->apiKey,
            'hasApiSecret' => $this->apiSecret !== null,
            'status' => $this->status->value,
            'lastError' => $this->lastError,
            'lastVerifiedAt' => $this->lastVerifiedAt?->format('c'),
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
