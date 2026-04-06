<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;

final readonly class SubscriptionStatusChangedEvent
{
    public function __construct(
        private Subscription $subscription,
        private string $previousStatus,
    ) {
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }
}
