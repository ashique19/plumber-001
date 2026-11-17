<?php

use App\Support\Invoicing\StandardInvoiceZatcaPayloadPrecheck;

it('allows vat registered standard buyer without cr number', function () {
    $payload = [
        'buyer_vat_registered' => true,
        'buyer_vat' => '300000000000003',
        'buyer_national_address' => [
            'building' => '12',
            'street' => 'King Fahd Road',
            'district' => 'Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12345',
            'country' => 'SA',
        ],
        'supply_date' => '2026-11-18',
    ];

    expect(StandardInvoiceZatcaPayloadPrecheck::validatePayload($payload))->toBe([]);
});

it('rejects non vat buyer without any identification', function () {
    $payload = [
        'buyer_vat_registered' => false,
        'buyer_national_address' => [
            'building' => '12',
            'street' => 'King Fahd Road',
            'district' => 'Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12345',
            'country' => 'SA',
        ],
        'supply_date' => '2026-11-18',
    ];

    $issues = StandardInvoiceZatcaPayloadPrecheck::validatePayload($payload);

    expect($issues)->toContain(__('invoices.zatca_precheck.buyer_vat_or_cr'));
});

it('rejects invalid cr format when cr is provided', function () {
    $payload = [
        'buyer_vat_registered' => true,
        'buyer_vat' => '300000000000003',
        'buyer_cr' => 'ABC12',
        'buyer_national_address' => [
            'building' => '12',
            'street' => 'King Fahd Road',
            'district' => 'Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12345',
            'country' => 'SA',
        ],
        'supply_date' => '2026-11-18',
    ];

    expect(StandardInvoiceZatcaPayloadPrecheck::validatePayload($payload))
        ->toContain(__('invoices.zatca_precheck.buyer_cr_invalid'));
});