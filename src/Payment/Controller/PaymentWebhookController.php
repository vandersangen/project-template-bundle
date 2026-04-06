<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Controller;

use VanDerSangen\ProjectTemplateBundle\Payment\Service\WebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentWebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookHandler $webhookHandler,
        private readonly string $webhookSecret,
    ) {
    }

    /**
     * Receives webhook payloads forwarded by the payment-api.
     * The payment-api sends a shared secret in the X-Webhook-Secret header.
     */
    #[Route('/api/payment/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        if ($this->webhookSecret !== '') {
            $payload = $request->getContent();
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookSecret);
            $incoming = $request->headers->get('X-Webhook-Secret', '');
            if (!hash_equals($expected, $incoming)) {
                return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $this->webhookHandler->handle($payload);

        return $this->json(['ok' => true]);
    }
}
