<?php

use App\Support\Invoicing\InvoicePrintPresenter;

it('builds full buyer address from national address parts for print', function () {
    $customer = InvoicePrintPresenter::customerFromPayload([
        'buyer_name' => 'washi',
        'buyer_national_address' => [
            'building' => '7229',
            'street' => 'King Fahd Rd',
            'district' => 'Al Olaya',
            'city' => 'RIYADH',
            'postal_code' => '12211',
            'country' => 'SA',
        ],
    ]);

    expect($customer)->not->toBeNull()
        ->and($customer['address_en_lines'])->toBe([
            '7229, King Fahd Rd',
            'Al Olaya, RIYADH 12211',
        ])
        ->and($customer['address_print'] ?? null)->toBeNull()
        ->and($customer['address'])->not->toContain('Kingdom of Saudi Arabia');
});