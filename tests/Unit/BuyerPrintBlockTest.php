<?php

use App\Support\Invoicing\BuyerPrintBlock;

it('builds bilingual buyer rows with arabic name stacked and hides empty fields', function () {
    $rows = BuyerPrintBlock::rows([
        'name_ar' => 'شركة مثال',
        'name_en' => 'Example Co',
        'address_line' => 'Al Olaya - King Fahd Rd',
        'city' => 'RIYADH',
        'post_box' => '3851',
        'vat_registered' => true,
        'vat_number' => '300000000000003',
    ]);

    expect($rows)->toHaveCount(5)
        ->and($rows[0]['en'])->toBe('Name')
        ->and($rows[0]['ar'])->toBe('الاسم')
        ->and($rows[0]['html'])->toContain('شركة مثال')
        ->and($rows[4]['en'])->toBe('VAT Number')
        ->and($rows[4]['value'])->toBe('300000000000003');
});

it('shows alternative id row when buyer is not vat registered', function () {
    $rows = BuyerPrintBlock::rows([
        'name' => 'Walk-in',
        'vat_registered' => false,
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1010998899',
        'city' => 'Jeddah',
    ]);

    expect(collect($rows)->pluck('en')->all())->toContain('CR Number')
        ->and(collect($rows)->firstWhere('en', 'CR Number')['value'])->toBe('1010998899');
});