<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

/**
 * Fired when the payment-api forwards a webhook from a payment provider.
 * The raw payload is available for custom handling in the consuming application.
 */
final readonly class WebhookReceivedEvent
{
    public function __construct(
        private string $type,
        private array $payload,
        private ?int $paymentApiSubscriptionId,
        private ?int $paymentApiPaymentId,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPaymentApiSubscriptionId(): ?int
    {
        return $this->paymentApiSubscriptionId;
    }

    public function getPaymentApiPaymentId(): ?int
    {
        return $this->paymentApiPaymentId;
    }
}
