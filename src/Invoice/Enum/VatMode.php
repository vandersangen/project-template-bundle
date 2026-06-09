<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Enum;

/**
 * Whether a charged amount already includes VAT (inclusive) or VAT must be
 * added on top (exclusive).
 */
enum VatMode: string
{
    case INCLUSIVE = 'inclusive';
    case EXCLUSIVE = 'exclusive';
}
