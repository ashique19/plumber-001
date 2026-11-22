<?php

use App\Support\Invoicing\BuyerAddressPrintHtml;

it('wraps pdf address parts in nowrap comma-bound spans', function () {
    $html = BuyerAddressPrintHtml::fromCustomer([
        'address_en_lines' => [
            '7780, Prince Abdulaziz bin Musaed bin Jalawi Street',
            'Al Murabba, Riyadh 12612, 3841',
        ],
    ], true);

    expect($html)
        ->toContain('invoice-pdf-address-part')
        ->toContain('<span class="invoice-pdf-address-part">Al Murabba</span>')
        ->toContain('<span class="invoice-pdf-address-part">Prince Abdulaziz bin Musaed bin Jalawi Street</span>')
        ->toContain('<span class="invoice-pdf-address-part">Riyadh 12612</span>');
});

it('keeps normal spaces in html screen address lines', function () {
    $html = BuyerAddressPrintHtml::fromCustomer([
        'address_en_lines' => ['Al Murabba, Riyadh 12612'],
    ], false);

    expect($html)->toContain('Al Murabba, Riyadh 12612')
        ->not->toContain('&nbsp;');
});

it('dedupes prebuilt address lines that already repeat district and city', function () {
    $html = BuyerAddressPrintHtml::fromCustomer([
        'address_en_lines' => [
            '7780, Prince Abdulaziz bin Musaed bin Jalawi Street,',
            'Al Murabba, Riyadh',
            'Al Murabba, Riyadh 12612, 3841',
        ],
    ], false);

    expect($html)
        ->toContain('7780, Prince Abdulaziz bin Musaed bin Jalawi Street')
        ->toContain('Al Murabba, Riyadh 12612, 3841')
        ->not->toContain('Street,')
        ->and(substr_count($html, 'invoice-a4__buyer-address-en__line'))->toBe(2)
        ->and(substr_count($html, 'Al Murabba'))->toBe(1);
});
