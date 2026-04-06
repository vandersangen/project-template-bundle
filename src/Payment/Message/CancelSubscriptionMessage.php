<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Message;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;

/**
 * @deprecated Not dispatched anywhere. Kept as public API for applications that want to defer
 *             subscription cancellation to the async queue. Use PaymentService::cancelSubscription() directly instead.
 */
final readonly class CancelSubscriptionMessage implements AsyncMessageInterface
{
    public function __construct(
        private int $subscriptionId,
        private bool $immediate = false,
        private ?string $reason = null,
        private bool $allowOneMoreCharge = false,
    ) {
    }

    public function getSubscriptionId(): int
    {
        return $this->subscriptionId;
    }

    public function isImmediate(): bool
    {
        return $this->immediate;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isAllowOneMoreCharge(): bool
    {
        return $this->allowOneMoreCharge;
    }
}
