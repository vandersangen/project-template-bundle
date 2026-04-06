<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Service;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\WebhookReceivedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Processes incoming webhook payloads forwarded by the payment-api.
 *
 * The payment-api sends a webhook to this tool's endpoint after receiving
 * a webhook from the actual payment provider (Stripe / Mollie).
 *
 * Expected payload structure:
 * {
 *   "type"|"event": "subscription.updated" | "payment.updated" | ...,  // both field names are supported
 *   "subscriptionId": 42,      // payment-api subscription ID (nullable)
 *   "paymentId": 123,          // payment-api payment ID (nullable)
 *   "data": { ... }            // event-specific data (field name may vary per provider)
 * }
 */
class WebhookHandler
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(array $payload): void
    {
        $type = $payload['type'] ?? $payload['event'] ?? 'unknown';
        $apiSubscriptionId = isset($payload['subscriptionId']) ? (int) $payload['subscriptionId'] : null;
        $apiPaymentId = isset($payload['paymentId']) ? (int) $payload['paymentId'] : null;

        $this->logger->info('Payment webhook received', [
            'type' => $type,
            'subscriptionId' => $apiSubscriptionId,
            'paymentId' => $apiPaymentId,
        ]);

        // Always dispatch the raw event so applications can hook in
        $this->eventDispatcher->dispatch(new WebhookReceivedEvent($type, $payload, $apiSubscriptionId, $apiPaymentId));

        if (str_starts_with($type, 'subscription.')) {
            $this->handleSubscriptionWebhook($type, $apiSubscriptionId, $apiPaymentId, $payload);
        } elseif (str_starts_with($type, 'payment.')) {
            $this->handlePaymentWebhook($apiPaymentId, $payload);
        }
    }

    private function handleSubscriptionWebhook(
        string $type,
        ?int $apiSubscriptionId,
        ?int $apiPaymentId,
        array $payload,
    ): void {
        if ($apiSubscriptionId === null) {
            return;
        }

        $subscription = $this->subscriptionRepository->findByPaymentApiId($apiSubscriptionId);
        if ($subscription === null) {
            return;
        }

        // Create a local payment record whenever a paymentId is present in the webhook.
        // This handles both 'subscription.payment.succeeded' and 'subscription.updated'
        // (which Mollie-based payment-apis may send instead for the initial mandate payment).
        if ($apiPaymentId !== null) {
            $existingPayment = $this->paymentRepository->findByPaymentApiId($apiPaymentId);

            if ($existingPayment === null) {
                $data = $payload['data'] ?? [];
                $payment = new Payment();
                $payment->setTenantId($subscription->getTenantId())
                    ->setUserId($subscription->getUserId())
                    ->setSubscription($subscription)
                    ->setPaymentApiPaymentId($apiPaymentId)
                    ->setProvider($subscription->getProvider())
                    ->setStatus(PaymentStatus::PENDING)
                    ->setAmountCents((int) ($data['amountCents'] ?? $subscription->getAmountCents()))
                    ->setCurrency($data['currency'] ?? $subscription->getCurrency())
                    ->setDescription($data['description'] ?? $subscription->getDescription())
                    ->setProviderPaymentId($data['providerPaymentId'] ?? null)
                    ->setUpdatedAt(new DateTimeImmutable());

                $this->paymentRepository->save($payment, true);

                $this->logger->info('Payment record created from subscription webhook', [
                    'type' => $type,
                    'paymentApiPaymentId' => $apiPaymentId,
                ]);

                // Let PaymentService handle charge counting and potential auto-cancellation
                $this->paymentService->syncPayment($payment);
            }
        }

        // Always re-sync the subscription state, forcing the payment-api to fetch
        // the latest status from the provider instead of returning cached data.
        try {
            $this->paymentService->syncSubscription($subscription, true);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync subscription after webhook', [
                'subscriptionId' => $subscription->getId(),
                'paymentApiSubscriptionId' => $apiSubscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handlePaymentWebhook(?int $apiPaymentId, array $payload): void
    {
        if ($apiPaymentId === null) {
            return;
        }

        $payment = $this->paymentRepository->findByPaymentApiId($apiPaymentId);

        if ($payment === null) {
            // Payment does not exist locally yet. If the payload references a subscription,
            // create the payment record now so it can be synced and tracked.
            $apiSubscriptionId = isset($payload['subscriptionId']) ? (int) $payload['subscriptionId'] : null;
            if ($apiSubscriptionId === null) {
                return;
            }

            $subscription = $this->subscriptionRepository->findByPaymentApiId($apiSubscriptionId);
            if ($subscription === null) {
                return;
            }

            $data = $payload['data'] ?? [];
            $payment = new Payment();
            $payment->setTenantId($subscription->getTenantId())
                ->setUserId($subscription->getUserId())
                ->setSubscription($subscription)
                ->setPaymentApiPaymentId($apiPaymentId)
                ->setProvider($subscription->getProvider())
                ->setStatus(PaymentStatus::PENDING)
                ->setAmountCents((int) ($data['amountCents'] ?? $subscription->getAmountCents()))
                ->setCurrency($data['currency'] ?? $subscription->getCurrency())
                ->setDescription($data['description'] ?? $subscription->getDescription())
                ->setProviderPaymentId($data['providerPaymentId'] ?? null)
                ->setUpdatedAt(new DateTimeImmutable());

            $this->paymentRepository->save($payment, true);
        }

        $this->paymentService->syncPayment($payment, true);
    }
}
