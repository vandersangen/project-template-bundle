<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Dto;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;

/**
 * Everything InvoiceService needs to build and persist one invoice. A single
 * VAT rate and mode apply to all lines (one rate per owner — see plan scope).
 */
final readonly class InvoiceData
{
    /**
     * @param PartyData                   $issuer
     * @param PartyData                   $customer
     * @param array<int, InvoiceLineData> $lines
     */
    public function __construct(
        public PartyData $issuer,
        public PartyData $customer,
        public array $lines,
        public int $vatRate,
        public VatMode $vatMode,
        public string $currency = 'EUR',
        public string $numberPrefix = '',
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public ?string $footerText = null,
        public ?string $accentColor = null,
        public ?DateTimeImmutable $issuedAt = null,
    ) {
    }
}
