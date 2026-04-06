<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Client\PaymentApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PaymentApiClientTest extends TestCase
{
    private function makeClient(MockResponse|array $responses): PaymentApiClient
    {
        $httpClient = new MockHttpClient(is_array($responses) ? $responses : [$responses]);
        return new PaymentApiClient($httpClient, 'http://payment-api.test', 'test-token');
    }

    public function testCreatePaymentSendsCorrectRequest(): void
    {
        $responseData = [
            'id' => 123,
            'provider' => 'mollie',
            'status' => 'pending',
            'checkoutUrl' => 'https://checkout.mollie.com/abc',
            'amountCents' => 999,
            'currency' => 'EUR',
        ];

        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 201,
            'response_headers' => ['Content-Type: application/json'],
        ]);

        $client = $this->makeClient($mock);
        $result = $client->createPayment('mollie', 999, 'https://example.com/return', 'EUR', 'Order #1');

        $this->assertSame(123, $result['id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('https://checkout.mollie.com/abc', $result['checkoutUrl']);

        $requestBody = json_decode((string) $mock->getRequestOptions()['body'], true);
        $this->assertSame('mollie', $requestBody['provider']);
        $this->assertSame(999, $requestBody['amount']);
        $this->assertSame('EUR', $requestBody['currency']);
        $this->assertSame('https://example.com/return', $requestBody['returnUrl']);
        $this->assertSame('Order #1', $requestBody['description']);
        $this->assertSame(
            'Authorization: Bearer test-token',
            $mock->getRequestOptions()['normalized_headers']['authorization'][0]
        );
    }

    public function testCreatePaymentWithCancelUrl(): void
    {
        $responseData = [
            'id' => 1,
            'status' => 'pending',
            'checkoutUrl' => 'http://c.url',
            'amountCents' => 100,
            'currency' => 'EUR',
            'provider' => 'stripe'
        ];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 201,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $client->createPayment('stripe', 100, 'https://return.url', 'EUR', null, 'https://cancel.url');

        $body = json_decode((string) $mock->getRequestOptions()['body'], true);
        $this->assertSame('https://cancel.url', $body['cancelUrl']);
    }

    public function testGetPaymentBuildsCorrectUrl(): void
    {
        $responseData = [
            'id' => 42,
            'provider' => 'stripe',
            'providerPaymentId' => 'pi_abc',
            'status' => 'paid',
            'amountCents' => 500,
            'currency' => 'EUR',
            'description' => 'Test',
            'createdAt' => '2026-03-14T12:00:00+00:00',
            'updatedAt' => null,
        ];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $result = $client->getPayment(42, true);

        $this->assertSame(42, $result['id']);
        $this->assertSame('paid', $result['status']);
        $this->assertStringContainsString('/api/v1/payments/42', $mock->getRequestUrl());
        $this->assertStringContainsString('sync=true', $mock->getRequestUrl());
    }

    public function testGetPaymentWithoutSync(): void
    {
        $responseData = [
            'id' => 1,
            'status' => 'pending',
            'amountCents' => 100,
            'currency' => 'EUR',
            'provider' => 'mollie',
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'updatedAt' => null,
            'description' => null,
            'providerPaymentId' => null,
        ];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $client->getPayment(1);

        $this->assertStringNotContainsString('sync', $mock->getRequestUrl());
    }

    public function testCreateSubscriptionSendsCorrectPayload(): void
    {
        $responseData = [
            'id' => 42,
            'provider' => 'mollie',
            'status' => 'active',
            'checkoutUrl' => 'https://checkout.mollie.com/sub',
            'amountCents' => 999,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'nextBillingDate' => '2026-04-14T00:00:00+00:00',
        ];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 201,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $result = $client->createSubscription(
            'mollie',
            'tenant-5',
            999,
            'https://return.url',
            'monthly',
            'EUR',
            'Premium'
        );

        $this->assertSame(42, $result['id']);
        $this->assertSame('active', $result['status']);

        $body = json_decode((string) $mock->getRequestOptions()['body'], true);
        $this->assertSame('mollie', $body['provider']);
        $this->assertSame('tenant-5', $body['toolUserReference']);
        $this->assertSame(999, $body['amountCents']);
        $this->assertSame('monthly', $body['interval']);
        $this->assertSame('Premium', $body['description']);
    }

    public function testGetSubscription(): void
    {
        $responseData = [
            'id' => 42,
            'toolUserReference' => 'tenant-5',
            'provider' => 'mollie',
            'providerSubscriptionId' => 'sub_abc',
            'providerCustomerId' => 'cus_xyz',
            'status' => 'active',
            'amountCents' => 999,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'description' => 'Premium',
            'nextBillingDate' => '2026-04-14T00:00:00+00:00',
            'failedChargeCount' => 0,
            'endsAt' => null,
            'createdAt' => '2026-03-14T00:00:00+00:00',
            'updatedAt' => null,
        ];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $result = $client->getSubscription(42);

        $this->assertSame(42, $result['id']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('sub_abc', $result['providerSubscriptionId']);
        $this->assertStringContainsString('/api/v1/subscriptions/42', $mock->getRequestUrl());
    }

    public function testCancelSubscriptionImmediately(): void
    {
        $responseData = ['id' => 42, 'status' => 'cancelled', 'endsAt' => null];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $result = $client->cancelSubscription(42, true, 'user_request');

        $this->assertSame('cancelled', $result['status']);
        $body = json_decode((string) $mock->getRequestOptions()['body'], true);
        $this->assertTrue($body['immediate']);
        $this->assertSame('user_request', $body['reason']);
        $this->assertSame('PATCH', $mock->getRequestMethod());
    }

    public function testCancelSubscriptionAtEndOfPeriod(): void
    {
        $responseData = ['id' => 42, 'status' => 'pending_cancellation', 'endsAt' => '2026-04-14T00:00:00+00:00'];
        $mock = new MockResponse(json_encode($responseData), [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);
        $client = $this->makeClient($mock);
        $result = $client->cancelSubscription(42, false);

        $this->assertSame('pending_cancellation', $result['status']);
        $body = json_decode((string) $mock->getRequestOptions()['body'], true);
        $this->assertFalse($body['immediate']);
    }

    public function testBearerTokenIsIncludedInAllRequests(): void
    {
        $headers = ['response_headers' => ['Content-Type: application/json']];
        $responses = [
            new MockResponse(
                json_encode([
                    'id' => 1,
                    'status' => 'pending',
                    'checkoutUrl' => 'http://url',
                    'amountCents' => 100,
                    'currency' => 'EUR',
                    'provider' => 'mollie'
                ]),
                ['http_code' => 201] + $headers
            ),
            new MockResponse(
                json_encode([
                    'id' => 1,
                    'status' => 'paid',
                    'amountCents' => 100,
                    'currency' => 'EUR',
                    'provider' => 'mollie',
                    'createdAt' => '2026-01-01T00:00:00+00:00',
                    'updatedAt' => null,
                    'description' => null,
                    'providerPaymentId' => null,
                ]),
                ['http_code' => 200] + $headers
            ),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new PaymentApiClient($httpClient, 'http://test', 'my-secret-token');

        $client->createPayment('mollie', 100, 'https://return.url');
        $client->getPayment(1);

        foreach ($responses as $response) {
            $this->assertSame(
                'Authorization: Bearer my-secret-token',
                $response->getRequestOptions()['normalized_headers']['authorization'][0]
            );
        }
    }
}
