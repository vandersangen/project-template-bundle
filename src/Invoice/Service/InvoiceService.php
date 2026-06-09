<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\InvoiceData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\InvoiceLineData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\Invoice;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceItem;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\InvoiceStatus;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceRepository;

/**
 * Builds and persists an Invoice from issuer/customer/line data, computing VAT
 * and assigning a sequential number. Generic and issuer-agnostic: the owning
 * application supplies an $ownerKey (e.g. "tool:42").
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly VatCalculator $vatCalculator,
    ) {
    }

    /**
     * Returns the existing invoice for a source if one already exists
     * (idempotency), otherwise builds, persists and returns a new one.
     */
    public function createFromData(string $ownerKey, InvoiceData $data): Invoice
    {
        if ($data->sourceType !== null && $data->sourceId !== null) {
            $existing = $this->invoiceRepository->findOneBySource($ownerKey, $data->sourceType, $data->sourceId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $issuedAt = $data->issuedAt ?? new DateTimeImmutable();

        $invoice = new Invoice();
        $invoice->setOwnerKey($ownerKey);
        $invoice->setSourceType($data->sourceType);
        $invoice->setSourceId($data->sourceId);
        $invoice->setIssuer($data->issuer->toArray());
        $invoice->setCustomer($data->customer->toArray());
        $invoice->setCurrency($data->currency);
        $invoice->setVatRate($data->vatRate);
        $invoice->setVatMode($data->vatMode);
        $invoice->setFooterText($data->footerText);
        $invoice->setAccentColor($data->accentColor);
        $invoice->setIssuedAt($issuedAt);
        $invoice->setStatus(InvoiceStatus::ISSUED);

        $netTotal = 0;
        $vatTotal = 0;
        $grossTotal = 0;

        foreach ($data->lines as $line) {
            $item = $this->buildItem($line, $data->vatRate, $data);
            $invoice->addItem($item);
            $netTotal += $item->getNetCents();
            $vatTotal += $item->getVatCents();
            $grossTotal += $item->getGrossCents();
        }

        $invoice->setNetCents($netTotal);
        $invoice->setVatCents($vatTotal);
        $invoice->setGrossCents($grossTotal);

        $number = $this->numberGenerator->next(
            $ownerKey,
            (int) $issuedAt->format('Y'),
            $data->numberPrefix,
        );
        $invoice->setNumber($number);

        $this->invoiceRepository->save($invoice, true);

        return $invoice;
    }

    private function buildItem(InvoiceLineData $line, int $vatRate, InvoiceData $data): InvoiceItem
    {
        $lineAmount = $line->quantity * $line->unitPriceCents;
        $split = $this->vatCalculator->split($lineAmount, $vatRate, $data->vatMode);

        $item = new InvoiceItem();
        $item->setDescription($line->description);
        $item->setQuantity($line->quantity);
        $item->setUnitPriceCents($line->unitPriceCents);
        $item->setVatRate($vatRate);
        $item->setNetCents($split['net']);
        $item->setVatCents($split['vat']);
        $item->setGrossCents($split['gross']);

        return $item;
    }
}
