<?php

namespace App\Support;

final class VatPricing
{
    public static function incFromEx(float $exVat, float $vatRate): float
    {
        return round($exVat * (1 + $vatRate / 100), 2);
    }

    public static function exFromInc(float $incVat, float $vatRate): float
    {
        $divisor = 1 + ($vatRate / 100);

        return $divisor > 0 ? round($incVat / $divisor, 2) : 0.0;
    }

    /**
     * Shelf-price consistent split: line inc = round(unit_inc × qty) minus discount (inc),
     * VAT = round(line_inc × rate / (100 + rate), 2), net = line_inc − VAT.
     *
     * @return array{line_inc: float, vat: float, net: float}
     */
    public static function lineAmountsFromUnitEx(float $unitEx, float $quantity, float $vatRate, float $discountExVat = 0.0): array
    {
        $rate = max(0.0, $vatRate);
        $unitInc = self::incFromEx($unitEx, $rate);
        $lineInc = round($unitInc * max(0, $quantity), 2);

        if ($discountExVat > 0) {
            $lineInc = max(0, $lineInc - self::incFromEx($discountExVat, $rate));
        }

        if ($rate <= 0) {
            return ['line_inc' => $lineInc, 'vat' => 0.0, 'net' => $lineInc];
        }

        $vat = round($lineInc * $rate / (100 + $rate), 2);
        $net = round($lineInc - $vat, 2);

        return ['line_inc' => $lineInc, 'vat' => $vat, 'net' => $net];
    }
}
