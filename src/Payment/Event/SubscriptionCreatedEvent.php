<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;

final readonly class SubscriptionCreatedEvent
{
    public function __construct(
        private Subscription $subscription,
    ) {
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }
}
