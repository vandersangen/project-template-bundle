<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Enum;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case PAST_DUE = 'past_due';
    case VERIFICATION_FAILED = 'verification_failed';
    case PENDING_CANCELLATION = 'pending_cancellation';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::ACTIVE, self::PAST_DUE, self::PENDING_CANCELLATION], true);
    }
}
