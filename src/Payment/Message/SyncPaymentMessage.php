<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Message;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;

/**
 * @deprecated Not dispatched anywhere. Kept as public API for applications that want to defer
 *             payment syncing to the async queue. Use PaymentService::syncPayment() directly instead.
 */
final readonly class SyncPaymentMessage implements AsyncMessageInterface
{
    public function __construct(
        private int $paymentId,
        private bool $forceSync = false,
    ) {
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function isForceSync(): bool
    {
        return $this->forceSync;
    }
}
