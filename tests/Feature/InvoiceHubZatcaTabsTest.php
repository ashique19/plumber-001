<?php

use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceAuditLog;
use App\Models\StaffUser;
use App\Business\BusinessContext;
use App\Support\Hub\HubInvoiceZatcaTab;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
    BusinessContext::clear();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->business->forceFill([
        'settings' => array_merge($this->business->settings ?? [], [
            'invoicing' => array_merge($this->business->settings['invoicing'] ?? [], [
                'integration_enabled' => true,
            ]),
        ]),
    ])->save();

    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
        'zheart.retry_times' => 0,
        'zheart.auto_submit_signed' => true,
    ]);

    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'invoice_hash' => 'hash-pos',
                'icv' => 1,
                'pih' => '',
                'qr' => 'cXI=',
            ],
        ], 200),
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'invoice_hash' => 'hash-pos',
                'icv' => 1,
                'qr' => 'cXI=',
                'zatca' => ['status' => 'reported'],
                'reporting_status' => 'reported',
            ],
        ], 200),
    ]);

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    BusinessContext::set($this->business);
    seedDemoPaidPosOrder();
});

function makeZatcaTestInvoice(array $payloadOverrides = []): Invoice
{
    $invoice = Invoice::query()->firstOrFail();

    $signedXml = array_key_exists('signed_xml', $payloadOverrides) ? $payloadOverrides['signed_xml'] : null;
    $icv = $payloadOverrides['icv'] ?? null;
    $invoiceHash = $payloadOverrides['invoice_hash'] ?? null;
    unset($payloadOverrides['signed_xml'], $payloadOverrides['icv'], $payloadOverrides['invoice_hash']);

    $payload = array_merge($invoice->payload ?? [], [
        'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440099',
        'signing_submission_status' => 'not_submitted',
        'zheart_remote_submitted' => false,
        'zheart_reporting_status' => 'pending',
        'lines' => $invoice->payload['lines'] ?? [[
            'name' => 'Service',
            'quantity' => 1,
            'unit_price_ex_vat' => 10,
            'vat_rate' => 15,
        ]],
    ], $payloadOverrides);

    DB::table('invoices')->where('id', $invoice->id)->update([
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'signed_xml' => $signedXml,
        'icv' => $icv,
        'invoice_hash' => $invoiceHash,
    ]);

    return $invoice->fresh();
}

it('filters invoices into zatca failed tab', function () {
    $failed = makeZatcaTestInvoice();

    seedDemoPaidPosOrder();
    $successInvoice = Invoice::query()->orderByDesc('id')->firstOrFail();
    $successNumber = $successInvoice->document_number;

    Invoice::withoutEvents(function () use ($successInvoice): void {
        $successInvoice->forceFill([
            'payload' => array_merge($successInvoice->payload ?? [], [
                'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440088',
                'zheart_remote_submitted' => true,
                'zheart_reporting_status' => 'reported',
                'signing_submission_status' => 'submitted',
                'zheart_zatca' => ['status' => 'reported'],
            ]),
        ])->save();
    });

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('zatcaTab', HubInvoiceZatcaTab::FAILED)
        ->assertSee($failed->document_number, false)
        ->assertDontSee($successNumber, false);
});

it('moves invoice to success state after resend', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'icv' => 7,
                'invoice_hash' => 'hash-7',
                'qr' => 'cXI=',
                'zatca' => ['status' => 'reported'],
            ],
            'request_id' => 'req-resend-ok',
        ], 200),
    ]);

    $invoice = makeZatcaTestInvoice([
        'zheart_last_error' => 'Previous rejection',
        'zheart_reporting_status' => 'rejected',
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('zatcaTab', HubInvoiceZatcaTab::FAILED)
        ->call('resubmitInvoice', $invoice->id);

    $invoice->refresh();
    expect(HubInvoiceZatcaTab::isSuccess($invoice))->toBeTrue()
        ->and(data_get($invoice->payload, 'zheart_reporting_status'))->toBe('reported');

    expect(InvoiceAuditLog::query()->where('invoice_id', $invoice->id)->where('event', 'invoice.zatca_resubmit')->exists())->toBeTrue();
});
