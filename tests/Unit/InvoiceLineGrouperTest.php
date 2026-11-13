<?php

use App\Support\Invoicing\InvoiceLineGrouper;

it('groups mixed service and part lines for display', function () {
    $lines = [
        ['name' => 'Leak visit', 'item_type' => 'service', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
        ['name' => 'PTFE tape', 'item_type' => 'part', 'quantity' => 2, 'unit_price_ex_vat' => 5, 'vat_rate' => 15],
    ];

    $grouped = InvoiceLineGrouper::sectionsForDisplay($lines, 'en');

    expect($grouped['uses_groups'])->toBeTrue()
        ->and($grouped['sections'])->toHaveCount(2)
        ->and($grouped['sections'][0]['key'])->toBe('services')
        ->and($grouped['sections'][1]['key'])->toBe('parts');
});

it('keeps cafe lines flat when types are not mixed trades group', function () {
    $lines = [
        ['name' => 'Latte', 'item_type' => 'item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
        ['name' => 'Espresso', 'quantity' => 1, 'unit_price_ex_vat' => 8, 'vat_rate' => 15],
    ];

    $grouped = InvoiceLineGrouper::sectionsForDisplay($lines, 'en');

    expect($grouped['uses_groups'])->toBeFalse()
        ->and($grouped['sections'][0]['lines'])->toHaveCount(2);
});
