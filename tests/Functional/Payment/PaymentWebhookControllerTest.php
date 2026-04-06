<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Service\WebhookHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookControllerTest extends WebTestCase
{
    private const string WEBHOOK_URL = '/api/payment/webhook';
    private const string WEBHOOK_SECRET = 'test-webhook-secret';

    private function computeSignature(string $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);
    }

    public function testWebhookWithValidSecretReturns200(): void
    {
        $webhookHandler = $this->createMock(WebhookHandler::class);
        $webhookHandler->expects($this->once())->method('handle');

        $client = static::createClient();
        $client->getContainer()->set(WebhookHandler::class, $webhookHandler);

        $body = json_encode(['type' => 'subscription.updated', 'subscriptionId' => 42]);
        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            self::WEBHOOK_URL,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Webhook-Secret' => $this->computeSignature($body),
            ],
            $body
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok']);
    }

    public function testWebhookWithWrongSecretReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            self::WEBHOOK_URL,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Webhook-Secret' => 'wrong-secret',
            ],
            json_encode(['type' => 'subscription.updated', 'subscriptionId' => 42])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testWebhookWithoutSecretHeaderReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            self::WEBHOOK_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'payment.updated', 'paymentId' => 99])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testWebhookWithInvalidJsonReturns400(): void
    {
        $client = static::createClient();
        $invalidBody = 'not-valid-json';

        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            self::WEBHOOK_URL,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Webhook-Secret' => $this->computeSignature($invalidBody),
            ],
            $invalidBody
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testWebhookOnlyAcceptsPostMethod(): void
    {
        $client = static::createClient();

        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_GET,
            self::WEBHOOK_URL,
            [],
            [],
            ['HTTP_X-Webhook-Secret' => self::WEBHOOK_SECRET]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testWebhookPassesPayloadToHandler(): void
    {
        $capturedPayload = null;
        $webhookHandler = $this->createMock(WebhookHandler::class);
        $webhookHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
            });

        $client = static::createClient();
        $client->getContainer()->set(WebhookHandler::class, $webhookHandler);

        $payload = [
            'type' => 'subscription.payment.succeeded',
            'subscriptionId' => 42,
            'paymentId' => 123,
            'data' => ['amountCents' => 999],
        ];

        $encodedPayload = json_encode($payload);
        $client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            self::WEBHOOK_URL,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Webhook-Secret' => $this->computeSignature($encodedPayload),
            ],
            $encodedPayload
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('subscription.payment.succeeded', $capturedPayload['type']);
        $this->assertSame(42, $capturedPayload['subscriptionId']);
        $this->assertSame(123, $capturedPayload['paymentId']);
    }
}
