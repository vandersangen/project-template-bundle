<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Dto;

/**
 * A single invoice line. The meaning of $unitPriceCents (net or gross) depends
 * on the invoice-level Vat mode; VAT is computed by InvoiceService.
 */
final readonly class InvoiceLineData
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
    ) {
    }
}
