# Shopify Module

Connects a tenant's Shopify store via a **merchant-created custom app** using Admin API
credentials — no OAuth flow and no public App Store app required.

## Why custom app credentials instead of OAuth?

Shopify's OAuth flow requires a published (App Store / partner) app. Until such an app
exists, merchants can create a **custom app** inside their own store and generate an
Admin API access token themselves. This module lets them paste those credentials into
the application.

## What the merchant does (in their Shopify admin)

1. Go to **Settings → Apps and sales channels → Develop apps** (enable custom app
   development if prompted).
2. Click **Create an app**, give it a name (e.g. the name of this application).
3. Under **Configuration → Admin API integration**, select the API scopes the
   application needs (e.g. `read_products`, `read_orders`).
4. Click **Install app** on the **API credentials** tab.
5. Reveal the **Admin API access token** (`shpat_...`) — it is shown **once**.
6. Paste the shop domain (`my-store.myshopify.com`) and the token into this
   application. Optionally also the **API key** and **API secret key** (the secret is
   used to validate webhook HMAC signatures).

## Endpoints

All endpoints require a JWT-authenticated user with a tenant.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/shopify/connection` | Connect: verifies the credentials against the Admin API (`shop.json`) and stores them. Body: `{shopDomain, accessToken, apiKey?, apiSecret?}` |
| `GET` | `/api/shopify/connection` | Current tenant's connection (never returns the token) |
| `POST` | `/api/shopify/connection/verify` | Re-verifies stored credentials, updates `status` / `lastError` |
| `DELETE` | `/api/shopify/connection` | Disconnect (removes stored credentials) |

The `shopDomain` input is normalized: `my-store`, `my-store.myshopify.com` and
`https://my-store.myshopify.com/` are all accepted.

Connect responds `422` when Shopify rejects the credentials (invalid token, missing
scopes, unknown shop) — nothing is stored in that case. Invalid input responds `400`.

## Security

- The access token and API secret key are **encrypted at rest** (libsodium
  `crypto_secretbox`) via `Shared\Service\CredentialEncryptor`, keyed from
  `project_template.encryption_secret` (defaults to `APP_SECRET`).
- API responses never contain the token or secret (`ShopifyConnection::toArray()`).

> Rotating `APP_SECRET` (or the configured `encryption_secret`) invalidates stored
> credentials; tenants must reconnect their store.

## Configuration

```yaml
# config/packages/project_template.yaml (all optional)
project_template:
    shopify:
        api_version: '2026-01'   # Shopify Admin API version
    encryption_secret: '%env(APP_SECRET)%'
```

## Using the connection in application code

```php
public function __construct(
    private ShopifyConnectionService $connectionService,
    private ShopifyApiClient $shopifyApiClient,
) {}

public function fetchProducts(int $tenantId): array
{
    $connection = $this->connectionService->getForTenant($tenantId);
    if ($connection === null) {
        throw new \RuntimeException('Tenant has no Shopify connection');
    }

    $accessToken = $this->connectionService->getDecryptedAccessToken($connection);

    // REST
    $products = $this->shopifyApiClient->get(
        $connection->getShopDomain(),
        $accessToken,
        'products.json',
        ['limit' => 50]
    );

    // Or GraphQL
    $result = $this->shopifyApiClient->graphql(
        $connection->getShopDomain(),
        $accessToken,
        '{ products(first: 50) { edges { node { id title } } } }'
    );

    return $products['products'] ?? [];
}
```

## Database

Migration `Version20260709000000` creates the `shopify_connections` table
(one connection per tenant, `tenant_id` unique).
