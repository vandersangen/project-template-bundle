<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Enum;

enum SubscriptionInterval: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
}
