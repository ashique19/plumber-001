<?php

use App\Services\Pos\PosTotalsCalculator;
use App\Support\VatPricing;

it('matches shelf price for espresso 11.99 inc vat times quantity', function () {
    $unitEx = VatPricing::exFromInc(11.99, 15);

    $calc = new PosTotalsCalculator;

    $totals3 = $calc->fromLines([
        ['name' => 'Espresso', 'quantity' => 3, 'unit_price_ex_vat' => $unitEx, 'vat_rate' => 15],
    ]);
    expect($totals3['total_inc_vat'])->toBe(35.97);

    $totals4 = $calc->fromLines([
        ['name' => 'Espresso', 'quantity' => 4, 'unit_price_ex_vat' => $unitEx, 'vat_rate' => 15],
    ]);
    expect($totals4['total_inc_vat'])->toBe(47.96);
});

it('matches shelf price for 20.70 inc vat times three', function () {
    $unitEx = VatPricing::exFromInc(20.70, 15);
    $calc = new PosTotalsCalculator;

    $totals = $calc->fromLines([
        ['name' => 'Item', 'quantity' => 3, 'unit_price_ex_vat' => $unitEx, 'vat_rate' => 15],
    ]);

    expect($totals['total_inc_vat'])->toBe(62.10);
});

it('sums mixed shelf-priced lines without drift', function () {
    $espressoEx = VatPricing::exFromInc(11.99, 15);
    $otherEx = VatPricing::exFromInc(20.70, 15);
    $calc = new PosTotalsCalculator;

    $totals = $calc->fromLines([
        ['name' => 'Espresso', 'quantity' => 2, 'unit_price_ex_vat' => $espressoEx, 'vat_rate' => 15],
        ['name' => 'Other', 'quantity' => 1, 'unit_price_ex_vat' => $otherEx, 'vat_rate' => 15],
    ]);

    expect($totals['total_inc_vat'])->toBe(44.68);
});
