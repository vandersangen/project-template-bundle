<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Client;

use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;

/**
 * HTTP client for the Shopify Admin API, authenticating with a per-shop
 * Admin API access token from a merchant-created custom app
 * (Shopify admin → Settings → Apps and sales channels → Develop apps).
 *
 * No OAuth involved: the merchant creates the app in their own store,
 * installs it and pastes the resulting shpat_... token into this application.
 */
class ShopifyApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiVersion,
    ) {
    }

    /**
     * Fetches the shop resource — also serves as a credentials check.
     *
     * @return array<string, mixed> The "shop" object from the Admin API
     */
    public function getShop(string $shopDomain, #[SensitiveParameter] string $accessToken): array
    {
        $data = $this->get($shopDomain, $accessToken, 'shop.json');

        return $data['shop'] ?? [];
    }

    /**
     * GET an Admin REST resource, e.g. "products.json" or "orders/123.json".
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(
        string $shopDomain,
        #[SensitiveParameter] string $accessToken,
        string $resource,
        array $query = [],
    ): array {
        return $this->request('GET', $shopDomain, $accessToken, $resource, ['query' => $query]);
    }

    /**
     * POST to an Admin REST resource.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(
        string $shopDomain,
        #[SensitiveParameter] string $accessToken,
        string $resource,
        array $body,
    ): array {
        return $this->request('POST', $shopDomain, $accessToken, $resource, ['json' => $body]);
    }

    /**
     * Executes an Admin GraphQL query.
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function graphql(
        string $shopDomain,
        #[SensitiveParameter] string $accessToken,
        string $query,
        array $variables = [],
    ): array {
        return $this->request('POST', $shopDomain, $accessToken, 'graphql.json', [
            'json' => ['query' => $query, 'variables' => $variables === [] ? null : $variables],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $shopDomain,
        #[SensitiveParameter] string $accessToken,
        string $resource,
        array $options = [],
    ): array {
        $url = sprintf('https://%s/admin/api/%s/%s', $shopDomain, $this->apiVersion, ltrim($resource, '/'));

        $options['headers'] = [
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return $response->toArray(false);
            }

            throw new ShopifyApiException(
                $this->extractErrorMessage($response->toArray(false), $statusCode),
                $statusCode
            );
        } catch (HttpClientExceptionInterface $exception) {
            throw new ShopifyApiException(
                sprintf('Could not reach Shopify at %s: %s', $shopDomain, $exception->getMessage()),
                0
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractErrorMessage(array $data, int $statusCode): string
    {
        $errors = $data['errors'] ?? null;
        if (is_string($errors) && $errors !== '') {
            return $errors;
        }
        if (is_array($errors) && $errors !== []) {
            return json_encode($errors) ?: 'Shopify API error';
        }

        return match ($statusCode) {
            401 => 'Invalid Shopify Admin API access token',
            403 => 'Access token is missing the required API scopes',
            404 => 'Shop or resource not found',
            429 => 'Shopify API rate limit exceeded',
            default => sprintf('Shopify API error (HTTP %d)', $statusCode),
        };
    }
}
