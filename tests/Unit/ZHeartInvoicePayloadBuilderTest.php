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
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1234567890',
            'buyer_national_address' => [
                'building' => '1234',
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '12345',
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
        ->and($payload['buyer']['address']['postal_code'])->toBe('12345');
});

it('rejects standard invoices without buyer vat', function () {
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
            'buyer_cr' => '1234567890',
        ],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440011',
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
