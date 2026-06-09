<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\VatCalculator;

class VatCalculatorTest extends TestCase
{
    private VatCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new VatCalculator();
    }

    public function testInclusiveSplitsVatOutOfGross(): void
    {
        // €121,00 incl. 21% -> net €100,00, vat €21,00, gross unchanged
        $result = $this->calculator->split(12100, 2100, VatMode::INCLUSIVE);

        self::assertSame(10000, $result['net']);
        self::assertSame(2100, $result['vat']);
        self::assertSame(12100, $result['gross']);
    }

    public function testExclusiveAddsVatOnTop(): void
    {
        // €100,00 excl. 21% -> vat €21,00, gross €121,00
        $result = $this->calculator->split(10000, 2100, VatMode::EXCLUSIVE);

        self::assertSame(10000, $result['net']);
        self::assertSame(2100, $result['vat']);
        self::assertSame(12100, $result['gross']);
    }

    public function testInclusiveTotalAlwaysEqualsChargedAmount(): void
    {
        // Net + vat must reconcile back to the gross that was charged.
        $result = $this->calculator->split(9999, 2100, VatMode::INCLUSIVE);

        self::assertSame(9999, $result['gross']);
        self::assertSame($result['gross'], $result['net'] + $result['vat']);
    }

    public function testZeroRateLeavesAmountUntouched(): void
    {
        $result = $this->calculator->split(5000, 0, VatMode::INCLUSIVE);

        self::assertSame(5000, $result['net']);
        self::assertSame(0, $result['vat']);
        self::assertSame(5000, $result['gross']);
    }
}
