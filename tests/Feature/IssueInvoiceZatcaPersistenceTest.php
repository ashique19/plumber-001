<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\InvoiceIssueService;
use App\Support\Invoicing\ZatcaResponseDisplay;
use App\Support\Invoicing\ZHeartInvoicePayloadFromInvoice;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::query()->create([
        'name' => 'Zatca Issue Co',
        'settings' => array_merge(
            PlumberThemePreset::settings('Zatca Issue Co', 'شركة', 'ar'),
            ['invoicing' => ['integration_enabled' => true]],
        ),
    ]);
    BusinessContext::set($business);

    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test_key',
        'zheart.auto_submit_signed' => true,
        'zheart.retry_times' => 0,
    ]);
});

function standardIssueDraft(string $number, array $payloadExtra = []): \App\Models\Invoice
{
    $branch = Branch::query()->first()
        ?? Branch::query()->create(['name' => 'Main', 'code' => 'main']);

    $draft = app(InvoiceIssueService::class)->issueDraft(
        $branch,
        $number,
        InvoiceDocumentType::Standard,
        [['name' => 'Work', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15]],
    );

    $draft->forceFill([
        'payload' => array_merge($draft->payload ?? [], array_merge([
            'invoice_category' => 'standard',
            'buyer_name' => 'TaskenLab Company',
            'buyer_vat' => '300000000000003',
            'buyer_vat_registered' => true,
            'buyer_cr' => '1012345678',
            'supply_date' => '2026-11-18',
            'buyer_national_address' => [
                'building' => '12',
                'street' => 'King Fahd Road',
                'district' => 'Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12345',
                'country' => 'SA',
            ],
        ], $payloadExtra)),
    ])->save();

    return $draft->fresh();
}

function zheartSignResponse(): array
{
    return [
        'status' => 'ok',
        'data' => [
            'signed_xml' => base64_encode('<Invoice/>'),
            'uuid' => (string) Str::uuid(),
            'invoice_hash' => 'hash-sign',
            'icv' => 40,
            'pih' => 'prev-hash',
            'qr' => 'cXI=',
        ],
        'request_id' => 'req-sign',
    ];
}

it('persists z-heart 422 request id and field errors when standard issue fails', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response(zheartSignResponse(), 200),
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'error',
            'message' => 'Validation failed.',
            'request_id' => 'req-issue-422',
            'data' => [
                'errors' => [
                    'buyer.address.postal' => ['Postal code required.'],
                ],
            ],
        ], 422, ['X-Request-Id' => 'req-issue-422']),
    ]);

    $draft = standardIssueDraft('STD-ISSUE-422');

    $outcome = app(InvoiceIssueService::class)->issue($draft);

    expect($outcome->signingSucceeded)->toBeFalse()
        ->and($outcome->signingFailureMessage)->not->toBeNull();

    $fresh = $outcome->invoice->fresh();
    expect(data_get($fresh->payload, 'zheart_request_id'))->toBe('req-issue-422')
        ->and(data_get($fresh->payload, 'zheart_last_http_status'))->toBe(422)
        ->and(data_get($fresh->payload, 'zheart_field_errors'))->not->toBeEmpty()
        ->and(data_get($fresh->payload, 'zheart_zatca_display.request_id'))->toBe('req-issue-422');

    $display = ZatcaResponseDisplay::forInvoice($fresh);
    expect($display['request_id'] ?? null)->toBe('req-issue-422');
});

it('marks signing failed when clear returns rejected clearance with request metadata', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response(zheartSignResponse(), 200),
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'request_id' => 'req-issue-reject',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => (string) Str::uuid(),
                'invoice_hash' => 'hash-reject',
                'icv' => 99,
                'pih' => 'prev-hash',
                'zatca' => [
                    'ok' => false,
                    'http_status' => 400,
                    'body' => [
                        'clearanceStatus' => 'NOT_CLEARED',
                        'validationResults' => [
                            'errorMessages' => [
                                ['message' => 'Buyer VAT invalid'],
                            ],
                        ],
                    ],
                ],
                'reporting_status' => 'rejected',
            ],
        ], 200),
    ]);

    $draft = standardIssueDraft('STD-ISSUE-REJECT');

    $outcome = app(InvoiceIssueService::class)->issue($draft);

    expect($outcome->signingSucceeded)->toBeFalse()
        ->and($outcome->invoice->signed_xml)->not->toBeNull()
        ->and(data_get($outcome->invoice->payload, 'zheart_request_id'))->toBe('req-issue-reject')
        ->and(data_get($outcome->invoice->payload, 'zheart_zatca_http_status'))->toBe(400)
        ->and(data_get($outcome->invoice->payload, 'zheart_reporting_status'))->toBe('pending')
        ->and(data_get($outcome->invoice->payload, 'zheart_zatca_delivery'))->toBe('rejected');
});

it('refreshes buyer address from customer before first standard issue submit', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response(zheartSignResponse(), 200),
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'request_id' => 'req-issue-ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                'uuid' => (string) Str::uuid(),
                'invoice_hash' => 'hash-ok',
                'icv' => 5,
                'pih' => '',
                'zatca' => [
                    'ok' => true,
                    'http_status' => 200,
                    'body' => ['clearanceStatus' => 'CLEARED'],
                ],
                'reporting_status' => 'cleared',
            ],
        ], 200),
    ]);

    $customer = Customer::query()->create([
        'name' => 'TaskenLab Company',
        'legal_name_en' => 'TaskenLab Company',
        'vat_number' => '300000000000003',
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1012345678',
        'country_code' => 'SA',
        'profile' => [
            'national_address' => [
                'building' => '55',
                'street' => 'Prince Sultan Rd',
                'district' => 'Malaz',
                'city' => 'Riyadh',
                'postal_code' => '11564',
                'country' => 'SA',
            ],
        ],
    ]);

    $draft = standardIssueDraft('STD-ISSUE-REFRESH', [
        'buyer_customer_id' => $customer->id,
        'buyer_national_address' => [
            'building' => '12',
            'street' => 'King Fahd Road',
            'city' => 'Riyadh',
            'country' => 'SA',
        ],
    ]);

    $outcome = app(InvoiceIssueService::class)->issue($draft);

    expect($outcome->signingSucceeded)->toBeTrue();

    $addr = data_get($outcome->invoice->payload, 'buyer_national_address');
    expect($addr['district'] ?? null)->toBe('Malaz')
        ->and($addr['postal_code'] ?? null)->toBe('11564');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/invoices/clear');
    });
    Http::assertSent(function ($request) {
        $encoded = json_encode($request->data());

        return str_contains($request->url(), '/invoices/clear')
            && str_contains((string) $encoded, '11564')
            && str_contains((string) $encoded, 'Malaz');
    });
});

it('sends the same clear payload shape on issue as on resubmit', function () {
  $clearBodies = [];
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response(zheartSignResponse(), 200),
        'https://z-heart.test/api/v1/invoices/clear' => function ($request) use (&$clearBodies) {
            $clearBodies[] = $request->data();

            return Http::response([
                'status' => 'ok',
                'request_id' => 'req-clear-parity',
                'data' => [
                    'signed_xml' => base64_encode('<Invoice/>'),
                    'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                    'uuid' => $clearBodies[0]['uuid'] ?? (string) Str::uuid(),
                    'invoice_hash' => 'hash-sign',
                    'zatca' => [
                        'ok' => true,
                        'http_status' => 200,
                        'body' => ['clearanceStatus' => 'CLEARED'],
                    ],
                    'reporting_status' => 'cleared',
                ],
            ], 200);
        },
    ]);

    $draft = standardIssueDraft('STD-PARITY-CLEAR');

    app(InvoiceIssueService::class)->issue($draft);
    expect($clearBodies)->toHaveCount(1);

    $invoice = Invoice::query()->where('document_number', 'STD-PARITY-CLEAR')->firstOrFail();
    $expected = ZHeartInvoicePayloadFromInvoice::build($invoice->fresh(), BusinessContext::require());
    $sent = $clearBodies[0]['invoice'] ?? $clearBodies[0];
    unset($sent['uuid'], $expected['uuid']);
    expect($sent)->toBe($expected);
});