<?php

namespace App\Services\Pos;

use App\Support\VatPricing;

class PosTotalsCalculator
{
    /**
     * @param  list<array{name: string, quantity: float, unit_price_ex_vat: float, vat_rate: float, discount_ex_vat?: float}>  $lines
     * @return array{subtotal_ex_vat: float, vat_total: float, total_inc_vat: float}
     */
    public function fromLines(array $lines, float $promoDiscountExVat = 0): array
    {
        $subtotal = 0.0;
        $vat = 0.0;
        $totalInc = 0.0;

        foreach ($lines as $line) {
            $amounts = VatPricing::lineAmountsFromUnitEx(
                (float) ($line['unit_price_ex_vat'] ?? 0),
                (float) ($line['quantity'] ?? 1),
                (float) ($line['vat_rate'] ?? 15),
                (float) ($line['discount_ex_vat'] ?? 0),
            );

            $subtotal += $amounts['net'];
            $vat += $amounts['vat'];
            $totalInc += $amounts['line_inc'];
        }

        $promo = max(0, $promoDiscountExVat);
        if ($promo > 0 && $subtotal > 0) {
            $ratio = max(0, $subtotal - $promo) / $subtotal;
            $vat *= $ratio;
            $subtotal -= $promo;
            $totalInc = $subtotal + $vat;
        }

        return [
            'subtotal_ex_vat' => round($subtotal, 2),
            'vat_total' => round($vat, 2),
            'total_inc_vat' => round($totalInc, 2),
        ];
    }
}
