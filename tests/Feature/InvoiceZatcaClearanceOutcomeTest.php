<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Http\Controllers\Admin\InvoiceZatcaResubmitController;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceZatcaBadge;
use App\Support\Hub\HubInvoiceZatcaTab;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();

    $business = BusinessSetting::singleton();
    $business->forceFill([
        'settings' => array_merge($business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true],
        ]),
    ])->save();
    BusinessContext::set($business);

    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test_key',
        'zheart.auto_submit_signed' => true,
    ]);

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

function clearancePendingStandardInvoice(string $number = 'STD-CLEAR-OUT-1'): Invoice
{
    return Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => $number,
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'icv' => 42,
        'invoice_hash' => 'hash-42',
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440077',
            'invoice_category' => 'standard',
            'signing_submission_status' => 'submitted',
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'pending',
            'zheart_zatca' => ['status' => 'pending'],
            'buyer_name' => 'Buyer',
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1012345678',
            'supply_date' => '2026-11-17',
            'buyer_national_address' => [
                'building' => '12',
                'street' => 'King Fahd Road',
                'district' => 'Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);
}

it('parses z-heart contract clearanceStatus CLEARED without legacy zatca.status', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'request_id' => '7457f736-4eda-455f-8b26-1c31da54c2f3',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'invoice_hash' => 'hash-42',
                'zatca' => [
                    'ok' => true,
                    'http_status' => 200,
                    'body' => [
                        'clearanceStatus' => 'CLEARED',
                        'validationResults' => ['status' => 'PASS'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-CONTRACT');

    app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    $fresh = $invoice->fresh();

    expect(HubInvoiceZatcaBadge::badgeKey($fresh))->toBe(HubInvoiceZatcaBadge::Cleared)
        ->and(data_get($fresh->payload, 'zheart_reporting_status'))->toBe('cleared')
        ->and(data_get($fresh->payload, 'zheart_zatca_display.clearance_status'))->toBe('CLEARED')
        ->and(data_get($fresh->payload, 'zheart_zatca_http_status'))->toBe(200)
        ->and(data_get($fresh->payload, 'zheart_zatca_delivery'))->toBe('sync');
});

it('flashes cleared and updates badge when z-heart clears a standard invoice', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'request_id' => 'req-clear-ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'invoice_hash' => 'hash-42',
                'zatca' => ['status' => 'cleared'],
                'reporting_status' => 'cleared',
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice();

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    expect($result->ok)->toBeTrue()
        ->and($result->flashLevel)->toBe('success')
        ->and($result->userMessage)->toBe(__('invoices.zatca_resubmit_cleared', ['number' => $invoice->document_number]));

    $fresh = $invoice->fresh();
    expect(HubInvoiceZatcaBadge::badgeKey($fresh))->toBe(HubInvoiceZatcaBadge::Cleared)
        ->and(HubInvoiceZatcaTab::isSuccess($fresh))->toBeTrue()
        ->and(data_get($fresh->payload, 'zheart_reporting_status'))->toBe('cleared');

    Http::assertSentCount(1);
});

it('flashes rejection with reason when z-heart rejects clearance', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => [
                    'status' => 'rejected',
                    'message' => 'Invalid buyer VAT number',
                ],
                'reporting_status' => 'rejected',
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-REJ');

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    expect($result->ok)->toBeFalse()
        ->and($result->flashLevel)->toBe('error')
        ->and($result->userMessage)->toBe(__('invoices.zatca_resubmit_rejected', [
            'number' => $invoice->document_number,
            'reason' => 'Invalid buyer VAT number',
        ]));

    $fresh = $invoice->fresh();
    expect(HubInvoiceZatcaBadge::badgeKey($fresh))->toBe(HubInvoiceZatcaBadge::Rejected)
        ->and(data_get($fresh->payload, 'zheart_last_error'))->toBe('Invalid buyer VAT number');
});

it('does not show generic success when clearance stays pending', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => ['status' => 'pending'],
                'reporting_status' => 'pending',
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-PEND');

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    expect($result->ok)->toBeFalse()
        ->and($result->flashLevel)->toBe('warning');

    $fresh = $invoice->fresh();
    expect(HubInvoiceZatcaBadge::badgeKey($fresh))->toBe(HubInvoiceZatcaBadge::ClearancePending)
        ->and(data_get($fresh->payload, 'zheart_last_error'))->toBeNull();
});

it('surfaces api errors from z-heart on resubmit', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'error',
            'message' => 'Gateway timeout',
        ], 503),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-ERR');

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    expect($result->ok)->toBeFalse()
        ->and($result->flashLevel)->toBe('error');

    $fresh = $invoice->fresh();
    expect(data_get($fresh->payload, 'zheart_last_error'))->not->toBeEmpty()
        ->and(data_get($fresh->payload, 'zheart_last_http_status'))->toBe(503);
});

it('always posts to invoices clear for pending standard invoices with signed xml', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => ['status' => 'pending'],
                'reporting_status' => 'pending',
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-POST');
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'icv' => null,
            'invoice_hash' => null,
        ])->save();
    });

    app(InvoiceZatcaResubmitService::class)->resubmit($invoice->fresh());

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/api/v1/invoices/clear'));
    Http::assertSentCount(1);
});

it('clears a stale in-flight flag and still posts clearance to z-heart', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => ['status' => 'pending'],
                'reporting_status' => 'pending',
            ],
        ], 200),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-FLIGHT');
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'zheart_submission_in_flight' => true,
            ]),
        ])->save();
    });

    app(InvoiceZatcaResubmitService::class)->resubmit($invoice->fresh());

    expect(data_get($invoice->fresh()->payload, 'zheart_submission_in_flight'))->toBeNull();
    Http::assertSentCount(1);
});

it('surfaces z-heart 422 validation on flash and stored http status', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'error',
            'message' => 'Invoice hash mismatch',
            'data' => ['error' => 'validation_failed'],
        ], 422, ['X-Request-Id' => 'req-422']),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-422');

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);
    $fresh = $invoice->fresh();

    expect($result->ok)->toBeFalse()
        ->and($result->userMessage)->toContain('422')
        ->and($result->userMessage)->toContain('Invoice hash mismatch')
        ->and(data_get($fresh->payload, 'zheart_last_http_status'))->toBe(422)
        ->and(data_get($fresh->payload, 'zheart_request_id'))->toBe('req-422');
});

it('lists field errors from z-heart 422 errors map on flash and payload without triplicating generic message', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'error',
            'message' => 'The invoice payload is invalid.',
            'errors' => [
                'buyer.address.district' => ['The district field is required.'],
                'buyer.cr_number' => ['CR number is required.'],
            ],
        ], 422, ['X-Request-Id' => '9cfd2820-3330-4149-a95e-b9ab7fd32b05']),
    ]);

    $invoice = clearancePendingStandardInvoice('STD-CLEAR-422-MAP');
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'buyer_cr' => '1012345678',
                'buyer_national_address' => [
                    'building' => '1',
                    'street' => 'King Fahd Rd',
                    'district' => 'Olaya',
                    'city' => 'Riyadh',
                    'postal_code' => '12345',
                ],
                'supply_date' => '2026-11-18',
            ]),
        ])->save();
    });

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice->fresh());
    $fresh = $invoice->fresh();

    expect($result->userMessage)->toContain(__('invoices.zatca_precheck.buyer_district'))
        ->and(substr_count(mb_strtolower($result->userMessage), mb_strtolower(__('invoices.zatca_precheck.failed'))))->toBeLessThanOrEqual(1)
        ->and(data_get($fresh->payload, 'zheart_field_errors'))->toBeArray()
        ->and(count(data_get($fresh->payload, 'zheart_field_errors')))->toBeGreaterThanOrEqual(2);
});

it('blocks resubmit locally when standard buyer fields are missing', function () {
    Http::fake();

    $invoice = clearancePendingStandardInvoice('STD-PRECHECK');
    Invoice::withoutEvents(function () use ($invoice): void {
        $payload = $invoice->payload ?? [];
        $address = (array) ($payload['buyer_national_address'] ?? []);
        unset($address['district']);
        $payload['buyer_national_address'] = $address;
        $invoice->forceFill(['payload' => $payload])->save();
    });

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice->fresh());

    expect($result->ok)->toBeFalse()
        ->and($result->userMessage)->toContain(__('invoices.zatca_precheck.buyer_district'));

    Http::assertNothingSent();
});

it('maps resubmit outcomes to flash keys on the invoice view controller', function () {
    $invoice = clearancePendingStandardInvoice('STD-CLEAR-CTRL');
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => ['status' => 'cleared'],
                'reporting_status' => 'cleared',
            ],
        ], 200),
    ]);

    $request = Request::create(
        route('admin.invoices.zatca-resubmit', $invoice),
        'POST',
        server: ['HTTP_REFERER' => route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4'])],
    );
    $request->setUserResolver(fn () => $this->owner);
    $request->setLaravelSession(app('session.store'));

    $response = app(InvoiceZatcaResubmitController::class)($request, $invoice, app(InvoiceZatcaResubmitService::class));

    expect($response->getSession()->get('success'))->toBe(__('invoices.zatca_resubmit_cleared', ['number' => $invoice->document_number]));
});

it('renders last z-heart response on invoice view without secrets', function () {
    $invoice = clearancePendingStandardInvoice('STD-CLEAR-VIEW');
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'zheart_zatca_display' => [
                    'status' => 'rejected',
                    'message' => 'Buyer VAT invalid',
                    'otp' => '123456',
                    'csid' => 'secret-csid',
                ],
                'zheart_request_id' => 'req-view-1',
            ]),
        ])->save();
    });

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice->fresh(), 'layout' => 'a4']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-testid="invoice-zatca-last-response"', false)
        ->toContain('Buyer VAT invalid')
        ->not->toContain('secret-csid')
        ->not->toContain('123456');
});

it('posts buyer address country and postal keys expected by z-heart on clear', function () {
    $postedPayload = null;

    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => function ($request) use (&$postedPayload) {
            $postedPayload = $request->data();

            return Http::response([
                'status' => 'ok',
                'data' => [
                    'signed_xml' => base64_encode('<Invoice/>'),
                    'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                    'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                    'zatca' => [
                        'ok' => true,
                        'http_status' => 200,
                        'body' => ['clearanceStatus' => 'CLEARED'],
                    ],
                ],
            ], 200);
        },
    ]);

    $invoice = clearancePendingStandardInvoice('STD-ADDR-KEYS');
    app(InvoiceZatcaResubmitService::class)->resubmit($invoice);

    Http::assertSentCount(1);
    expect($postedPayload)->not->toBeNull()
        ->and(data_get($postedPayload, 'invoice.buyer.address.country'))->toBe('SA')
        ->and(data_get($postedPayload, 'invoice.buyer.address.postal'))->toBe('12345')
        ->and(data_get($postedPayload, 'invoice.buyer.address.postalZone'))->toBe('12345')
        ->and(data_get($postedPayload, 'invoice.buyer.address.buildingNumber'))->toBe('12');
});

it('persists refreshed buyer postal before posting clear to z-heart', function () {
    $customer = Customer::query()->where('phone', '0501000001')->firstOrFail();
    $profile = is_array($customer->profile) ? $customer->profile : [];
    $profile['national_address'] = array_merge(
        is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [],
        [
            'building' => '9021',
            'street' => 'Prince Mohammed Bin Abdulaziz Rd',
            'district' => 'Al Olaya',
            'city' => 'Riyadh',
            'postal_code' => '11564',
            'country' => 'SA',
        ],
    );
    $customer->forceFill(['profile' => $profile])->save();

    $invoice = clearancePendingStandardInvoice('STD-REFRESH-POSTAL');
    Invoice::withoutEvents(function () use ($invoice, $customer): void {
        $na = (array) ($invoice->payload['buyer_national_address'] ?? []);
        unset($na['postal_code']);
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'buyer_customer_id' => $customer->id,
                'buyer_phone' => $customer->phone,
                'buyer_national_address' => $na,
            ]),
        ])->save();
    });

    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440077',
                'zatca' => [
                    'ok' => true,
                    'http_status' => 200,
                    'body' => ['clearanceStatus' => 'CLEARED'],
                ],
            ],
        ], 200),
    ]);

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice->fresh());

    expect($result->ok)->toBeTrue()
        ->and(data_get($invoice->fresh()->payload, 'buyer_national_address.postal_code'))->toBe('11564');
    Http::assertSentCount(1);
});