<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Services\Invoicing\Signing\ZHeartInvoiceSigningGateway;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
        'zheart.retry_times' => 0,
        'zheart.auto_submit_signed' => true,
    ]);
});

it('maps data.zatca from report responses', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'invoice_hash' => 'aGFzaA==',
                'qr' => 'cXI=',
                'uuid' => '550e8400-e29b-41d4-a716-446655440020',
                'icv' => 2,
                'pih' => '',
                'zatca' => [
                    'status' => 'reported',
                    'uuid' => 'zatca-uuid-1',
                ],
            ],
            'request_id' => 'req-report',
        ], 200),
    ]);

    $business = BusinessSetting::query()->create([
        'name' => 'Gateway Co',
        'settings' => array_merge(PlumberThemePreset::settings('Gateway Co', 'بوابة', 'ar'), [
            'invoicing' => ['integration_enabled' => true, 'default_document_type' => 'simplified'],
        ]),
    ]);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: 'POS-88',
        documentType: InvoiceDocumentType::Simplified,
        invoiceCategory: 'simplified',
        lines: [new \App\Data\InvoiceLineData('Leak', 1, 20, 15)],
        invoicePayload: [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: '550e8400-e29b-41d4-a716-446655440020',
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $invoice = new Invoice(['document_number' => 'POS-88']);
    $result = app(ZHeartInvoiceSigningGateway::class)->signIssuedInvoice($request, $invoice);

    expect($result['remote_submission']['status'])->toBe('reported')
        ->and($result['reporting_status'])->toBe('reported')
        ->and($result['remote_submitted'])->toBeTrue();
});
