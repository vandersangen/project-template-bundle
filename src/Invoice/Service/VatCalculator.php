<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;

/**
 * Splits an amount into net/VAT/gross given a rate and mode. VAT rate is in
 * basis points (10000 = 100%, so 2100 = 21%).
 *
 *  - INCLUSIVE: the amount is the gross; VAT is calculated out of it
 *    (net = gross / (1 + rate)). The invoice total equals the charged amount.
 *  - EXCLUSIVE: the amount is the net; VAT is added on top
 *    (vat = net * rate, gross = net + vat).
 */
final class VatCalculator
{
    private const int BASIS = 10000;

    /**
     * @return array{net: int, vat: int, gross: int}
     */
    public function split(int $amountCents, int $vatRate, VatMode $mode): array
    {
        if ($vatRate <= 0) {
            return ['net' => $amountCents, 'vat' => 0, 'gross' => $amountCents];
        }

        if ($mode === VatMode::EXCLUSIVE) {
            $net = $amountCents;
            $vat = (int) round($net * $vatRate / self::BASIS);
            return ['net' => $net, 'vat' => $vat, 'gross' => $net + $vat];
        }

        // INCLUSIVE
        $gross = $amountCents;
        $net = (int) round($gross * self::BASIS / (self::BASIS + $vatRate));
        return ['net' => $net, 'vat' => $gross - $net, 'gross' => $gross];
    }
}
