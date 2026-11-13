<?php

use App\Services\Pos\PosTotalsCalculator;

it('calculates VAT on net ex-VAT after line discounts', function () {
    $calc = new PosTotalsCalculator;

    $totals = $calc->fromLines([
        ['name' => 'Latte', 'quantity' => 2, 'unit_price_ex_vat' => 50, 'vat_rate' => 15, 'discount_ex_vat' => 10],
    ]);

    expect($totals['subtotal_ex_vat'])->toBe(90.0)
        ->and($totals['vat_total'])->toBe(13.5)
        ->and($totals['total_inc_vat'])->toBe(103.5);
});

it('applies order-level promo discount ex-VAT', function () {
    $calc = new PosTotalsCalculator;

    $totals = $calc->fromLines([
        ['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15, 'discount_ex_vat' => 0],
    ], promoDiscountExVat: 10);

    expect($totals['subtotal_ex_vat'])->toBe(90.0)
        ->and($totals['vat_total'])->toBe(13.5);
});
