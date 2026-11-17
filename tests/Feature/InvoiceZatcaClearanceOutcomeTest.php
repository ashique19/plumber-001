<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
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
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);
}

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

    expect(data_get($invoice->fresh()->payload, 'zheart_last_error'))->not->toBeEmpty();
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