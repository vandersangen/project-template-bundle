<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Enum;

enum SubscriptionInterval: string
{
    case MONTHLY       = 'monthly';
    case QUARTERLY     = 'quarterly';
    case YEARLY        = 'yearly';
    case WEEKLY        = '1 week';
    case EVERY_3_DAYS  = '3 days';
    case EVERY_2_DAYS  = '2 days';
}
