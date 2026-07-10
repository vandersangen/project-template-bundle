<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the central payment-api.
 * Authenticates via Bearer JWT configured in project_template.payment.api_token.
 */
class PaymentApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiToken,
    ) {
    }

    /**
     * @return array{id: int, provider: string, status: string, checkoutUrl: string, amountCents: int, currency: string}
     */
    public function createPayment(
        string $provider,
        int $amountCents,
        string $returnUrl,
        string $currency = 'EUR',
        ?string $description = null,
        ?string $cancelUrl = null,
    ): array {
        $body = [
            'provider' => $provider,
            'amount' => $amountCents,
            'currency' => $currency,
            'returnUrl' => $returnUrl,
        ];

        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($cancelUrl !== null) {
            $body['cancelUrl'] = $cancelUrl;
        }

        return $this->post('/api/v1/payments', $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(int $id, bool $sync = false): array
    {
        $url = sprintf('/api/v1/payments/%d', $id);
        if ($sync) {
            $url .= '?sync=true';
        }

        return $this->get($url);
    }

    /**
     * @return array{billingDay: int}
     */
    public function getToolSettings(): array
    {
        return $this->get('/api/v1/tool/settings');
    }

    /**
     * @param string                     $provider          Payment provider (stripe or mollie).
     * @param string                     $toolUserReference Tool-internal user identifier.
     * @param int                        $amountCents       Recurring amount in cents.
     * @param string                     $returnUrl         URL to redirect to after checkout.
     * @param string                     $interval          Billing interval.
     * @param string                     $currency          Three-letter ISO currency code.
     * @param string|null                $description       Subscription description.
     * @param string|null                $cancelUrl         URL to redirect to on cancel.
     * @param int                        $billingDay        Day of the month to bill on.
     * @param array<string, string>|null $customer          Customer billing details used on
     *                                                      generated invoices (name, companyName,
     *                                                      email, vatNumber, cocNumber, street,
     *                                                      houseNumber, postalCode, city, country).
     *
     * @return array{
     *     id: int, provider: string, status: string, checkoutUrl: string,
     *     amountCents: int, currency: string, interval: string, nextBillingDate: string
     * }
     */
    public function createSubscription(
        string $provider,
        string $toolUserReference,
        int $amountCents,
        string $returnUrl,
        string $interval = 'monthly',
        string $currency = 'EUR',
        ?string $description = null,
        ?string $cancelUrl = null,
        int $billingDay = 1,
        ?array $customer = null,
    ): array {
        $body = [
            'provider' => $provider,
            'toolUserReference' => $toolUserReference,
            'amountCents' => $amountCents,
            'currency' => $currency,
            'interval' => $interval,
            'returnUrl' => $returnUrl,
            'billingDay' => $billingDay,
        ];

        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($cancelUrl !== null) {
            $body['cancelUrl'] = $cancelUrl;
        }
        if ($customer !== null && $customer !== []) {
            $body['customer'] = $customer;
        }

        return $this->post('/api/v1/subscriptions', $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(int $id, bool $sync = false): array
    {
        $url = sprintf('/api/v1/subscriptions/%d', $id);
        if ($sync) {
            $url .= '?sync=true';
        }

        return $this->get($url);
    }

    /**
     * @return array{id: int, checkoutUrl: string, status: string, amountCents: int, currency: string}
     */
    public function retrySubscriptionPayment(int $subscriptionId, string $returnUrl, ?string $cancelUrl = null): array
    {
        $body = ['returnUrl' => $returnUrl];
        if ($cancelUrl !== null) {
            $body['cancelUrl'] = $cancelUrl;
        }

        return $this->post(sprintf('/api/v1/subscriptions/%d/retry-payment', $subscriptionId), $body);
    }

    /**
     * @return array{id: int, status: string, endsAt: string|null}
     */
    public function cancelSubscription(int $id, bool $immediate = false, ?string $reason = null): array
    {
        $body = ['immediate' => $immediate];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        return $this->patch(sprintf('/api/v1/subscriptions/%d/cancel', $id), $body);
    }

    private function get(string $path): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . $path, [
            'headers' => $this->buildHeaders(),
        ]);

        return $response->toArray();
    }

    private function post(string $path, array $body): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
            'headers' => $this->buildHeaders(),
            'json' => $body,
        ]);

        return $response->toArray();
    }

    private function patch(string $path, array $body): array
    {
        $response = $this->httpClient->request('PATCH', $this->baseUrl . $path, [
            'headers' => $this->buildHeaders(),
            'json' => $body,
        ]);

        return $response->toArray();
    }

    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
