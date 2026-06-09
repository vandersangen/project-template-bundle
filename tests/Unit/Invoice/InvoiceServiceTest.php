<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\InvoiceData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\InvoiceLineData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\PartyData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\Invoice;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\InvoiceStatus;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceRepository;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\InvoiceNumberGenerator;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\InvoiceService;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\VatCalculator;

class InvoiceServiceTest extends TestCase
{
    private InvoiceRepository $repository;
    private InvoiceNumberGenerator $numberGenerator;
    private InvoiceService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepository::class);
        $this->numberGenerator = $this->createMock(InvoiceNumberGenerator::class);
        $this->service = new InvoiceService(
            $this->repository,
            $this->numberGenerator,
            new VatCalculator(),
        );
    }

    public function testCreatesInvoiceWithVatBreakdownAndNumber(): void
    {
        $this->repository->method('findOneBySource')->willReturn(null);
        $this->numberGenerator->expects(self::once())
            ->method('next')
            ->with('tool:1', self::isType('int'), 'INV-')
            ->willReturn('INV-2026-00001');
        $this->repository->expects(self::once())->method('save');

        $invoice = $this->service->createFromData('tool:1', $this->data(VatMode::INCLUSIVE, 12100, 'INV-'));

        self::assertSame('INV-2026-00001', $invoice->getNumber());
        self::assertSame(10000, $invoice->getNetCents());
        self::assertSame(2100, $invoice->getVatCents());
        self::assertSame(12100, $invoice->getGrossCents());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->getStatus());
        self::assertCount(1, $invoice->getItems());
    }

    public function testExclusiveModeAddsVatOnTop(): void
    {
        $this->repository->method('findOneBySource')->willReturn(null);
        $this->numberGenerator->method('next')->willReturn('2026-00002');

        $invoice = $this->service->createFromData('tool:1', $this->data(VatMode::EXCLUSIVE, 10000));

        self::assertSame(10000, $invoice->getNetCents());
        self::assertSame(2100, $invoice->getVatCents());
        self::assertSame(12100, $invoice->getGrossCents());
    }

    public function testIsIdempotentForSameSource(): void
    {
        $existing = new Invoice();
        $this->repository->expects(self::once())
            ->method('findOneBySource')
            ->with('tool:1', 'payment', '42')
            ->willReturn($existing);
        $this->numberGenerator->expects(self::never())->method('next');
        $this->repository->expects(self::never())->method('save');

        $invoice = $this->service->createFromData('tool:1', $this->data(VatMode::INCLUSIVE, 12100));

        self::assertSame($existing, $invoice);
    }

    private function data(VatMode $mode, int $amountCents, string $prefix = ''): InvoiceData
    {
        return new InvoiceData(
            issuer: new PartyData(companyName: 'Acme BV'),
            customer: new PartyData(name: 'Klant', email: 'klant@example.com'),
            lines: [new InvoiceLineData('Abonnement', 1, $amountCents)],
            vatRate: 2100,
            vatMode: $mode,
            currency: 'EUR',
            numberPrefix: $prefix,
            sourceType: 'payment',
            sourceId: '42',
        );
    }
}
