<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Event;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;

final readonly class PaymentCreatedEvent
{
    public function __construct(
        private Payment $payment,
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }
}
