<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Support\Carbon;

it('includes buyer address, vat, and cr for standard invoices', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'STD-100',
        documentType: InvoiceDocumentType::Standard,
        invoiceCategory: 'standard',
        lines: [new InvoiceLineData('Service', 1, 100, 15)],
        invoicePayload: [
            'buyer_name' => 'Acme LLC',
            'buyer_name_en' => 'Acme LLC',
            'buyer_vat_registered' => true,
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1234567890',
            'buyer_national_address' => [
                'building' => '1234',
                'street' => 'King Fahd Rd',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12345',
                'country' => 'SA',
            ],
        ],
        issueDateTimeUtc: Carbon::parse('2026-01-15 09:00:00', 'UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440010',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['buyer']['vat_number'])->toBe('300000000000003')
        ->and($payload['buyer']['cr_number'])->toBe('1234567890')
        ->and($payload['buyer']['address']['country'])->toBe('SA')
        ->and($payload['buyer']['address']['postal'])->toBe('12345')
        ->and($payload['buyer']['address']['postalZone'])->toBe('12345')
        ->and($payload['buyer']['address']['buildingNumber'])->toBe('1234')
        ->and($payload['buyer']['address']['subdivision'])->toBe('Al Olaya');
});

it('builds standard invoices with buyer cr only', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'STD-101',
        documentType: InvoiceDocumentType::Standard,
        invoiceCategory: 'standard',
        lines: [new InvoiceLineData('Service', 1, 100, 15)],
        invoicePayload: [
            'buyer_name' => 'Acme LLC',
            'buyer_vat_registered' => false,
            'buyer_alt_id_type' => 'CRN',
            'buyer_alt_id_number' => '1234567890',
            'buyer_cr' => '1234567890',
        ],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440011',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['buyer']['cr_number'])->toBe('1234567890')
        ->and($payload['buyer']['party_identification']['scheme_id'])->toBe('CRN')
        ->and($payload['buyer'])->not->toHaveKey('vat_number');
});

it('rejects standard invoices without buyer vat or cr', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'STD-102',
        documentType: InvoiceDocumentType::Standard,
        invoiceCategory: 'standard',
        lines: [new InvoiceLineData('Service', 1, 100, 15)],
        invoicePayload: [
            'buyer_name' => 'Acme LLC',
        ],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440013',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    expect(fn () => app(ZHeartInvoicePayloadBuilder::class)->build($request))
        ->toThrow(InvalidArgumentException::class);
});

it('sends credit note parent uuid and reason', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'CN-1',
        documentType: InvoiceDocumentType::CreditNote,
        invoiceCategory: 'simplified',
        lines: [new InvoiceLineData('Refund', 1, 50, 15)],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440012',
        referencedInvoiceNumber: 'POS-1',
        referencedInvoiceUuid: '550e8400-e29b-41d4-a716-446655440099',
        noteReason: 'Customer return',
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['billing_reference_uuid'])->toBe('550e8400-e29b-41d4-a716-446655440099')
        ->and($payload['payment_means_note'])->toBe('Customer return');
});

it('includes line discounts when present', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'SIM-1',
        documentType: InvoiceDocumentType::Simplified,
        invoiceCategory: 'simplified',
        lines: [new InvoiceLineData('Pipe', 2, 40, 15, discountExVat: 5)],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440013',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['lines'][0]['discount'])->toBe(5.0);
});

it('sends decimal quantities and document discount without retention in payload', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'SIM-2',
        documentType: InvoiceDocumentType::Simplified,
        invoiceCategory: 'simplified',
        lines: [new InvoiceLineData('Progress', 0.05, 29_150, 15)],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440014',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
        documentDiscountExVat: 25,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['lines'][0]['quantity'])->toBe(0.05)
        ->and($payload['document_discount_ex_vat'])->toBe(25.0)
        ->and($payload['totals']['tax_exclusive'])->toBe(1432.50);
});

it('maps document level discount for z-heart totals', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Payload Co',
        'settings' => PlumberThemePreset::settings('Payload Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'SIM-3',
        documentType: InvoiceDocumentType::Simplified,
        invoiceCategory: 'simplified',
        lines: [new InvoiceLineData('Item', 1, 100, 15)],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440015',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
        documentDiscountExVat: 10,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['totals']['tax_exclusive'])->toBe(90.0)
        ->and($payload['totals']['tax'])->toBe(13.5);
});

it('emits ubl unit code and vat category on lines', function () {
    $business = BusinessSetting::query()->create([
        'name' => 'Units Co',
        'settings' => PlumberThemePreset::settings('Units Co', 'شركة', 'ar'),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'SIM-20',
        documentType: InvoiceDocumentType::Simplified,
        invoiceCategory: 'simplified',
        lines: [new InvoiceLineData('Pipe', 2, 40, 15, unitCode: 'PCE', vatZatcaCategory: 'S')],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440099',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['lines'][0]['unit_code'])->toBe('PCE')
        ->and($payload['lines'][0]['vat_category'])->toBe('S');
});
