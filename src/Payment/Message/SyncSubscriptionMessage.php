<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Message;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;

/**
 * @deprecated Not dispatched anywhere. Kept as public API for applications that want to defer
 *             subscription syncing to the async queue. Use PaymentService::syncSubscription() directly instead.
 */
final readonly class SyncSubscriptionMessage implements AsyncMessageInterface
{
    public function __construct(
        private int $subscriptionId,
    ) {
    }

    public function getSubscriptionId(): int
    {
        return $this->subscriptionId;
    }
}
