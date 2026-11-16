<?php

use App\Support\Invoicing\BuyerPhonePrint;
use App\Support\Invoicing\BuyerPrintBlock;

it('builds bilingual buyer rows with customer name and hides empty fields', function () {
    $rows = BuyerPrintBlock::rows([
        'name_ar' => 'شركة مثال',
        'name_en' => 'Example Co',
        'address_print' => '7229, King Fahd Rd, Al Olaya, RIYADH, 12211, Kingdom of Saudi Arabia',
        'email' => 'buyer@example.test',
        'phone' => '0562331303',
        'vat_registered' => true,
        'vat_number' => '300000000000003',
    ]);

    expect($rows)->toHaveCount(5)
        ->and($rows[0]['en'])->toBe('Customer')
        ->and($rows[0]['ar'])->toBe('العميل')
        ->and($rows[0]['html'])->toContain('شركة مثال')
        ->and(collect($rows)->firstWhere('en', 'Address')['value'])->toContain('Kingdom of Saudi Arabia')
        ->and(collect($rows)->firstWhere('en', 'Phone')['value'])->toBe('+966 562331303')
        ->and(collect($rows)->firstWhere('en', 'VAT number')['ar'])->toBe('رقم التسجيل الضريبي');
});

it('composes address from parts when address_print is absent', function () {
    $rows = BuyerPrintBlock::rows([
        'name' => 'Buyer',
        'address_parts' => [
            'building' => '9261',
            'street' => 'Eastern Ring Road',
            'district' => 'Ar Riyadh',
            'city' => 'Riyadh',
            'postal_code' => '11413',
        ],
        'country_code' => 'SA',
    ]);

    $address = collect($rows)->firstWhere('en', 'Address')['value'];

    expect($address)->toBe('9261, Eastern Ring Road, Ar Riyadh, Riyadh, 11413, Kingdom of Saudi Arabia');
});

it('shows alternative id row when buyer is not vat registered', function () {
    $rows = BuyerPrintBlock::rows([
        'name' => 'Walk-in',
        'vat_registered' => false,
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1010998899',
    ]);

    expect(collect($rows)->pluck('en')->all())->toContain('CR Number')
        ->and(collect($rows)->firstWhere('en', 'CR Number')['value'])->toBe('1010998899');
});

it('formats saudi mobile numbers for print', function () {
    expect(BuyerPhonePrint::format('0562331303'))->toBe('+966 562331303')
        ->and(BuyerPhonePrint::format('+966562331303'))->toBe('+966 562331303')
        ->and(BuyerPhonePrint::format('562331303'))->toBe('+966 562331303');
});