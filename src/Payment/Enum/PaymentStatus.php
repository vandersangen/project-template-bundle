<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
}
