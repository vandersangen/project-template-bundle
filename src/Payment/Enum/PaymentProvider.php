<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Enum;

enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case MOLLIE = 'mollie';
}
