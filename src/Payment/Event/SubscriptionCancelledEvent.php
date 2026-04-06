<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;

final readonly class SubscriptionCancelledEvent
{
    public function __construct(
        private Subscription $subscription,
        private bool $immediate,
        private ?string $reason,
    ) {
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function isImmediate(): bool
    {
        return $this->immediate;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
