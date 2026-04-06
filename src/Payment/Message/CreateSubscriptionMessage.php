<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Message;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;

/**
 * @deprecated Not dispatched anywhere. Kept as public API for applications that want to defer
 *             subscription creation to the async queue. Use PaymentService::createSubscription() directly instead.
 */
final readonly class CreateSubscriptionMessage implements AsyncMessageInterface
{
    public function __construct(
        private int $tenantId,
        private int $userId,
        private string $provider,
        private int $amountCents,
        private string $interval,
        private string $returnUrl,
        private string $currency = 'EUR',
        private ?string $description = null,
        private ?string $cancelUrl = null,
    ) {
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCancelUrl(): ?string
    {
        return $this->cancelUrl;
    }
}
