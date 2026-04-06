<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;

final readonly class PaymentStatusChangedEvent
{
    public function __construct(
        private Payment $payment,
        private string $previousStatus,
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }
}
