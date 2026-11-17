<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Business\BusinessContext;
use App\Livewire\Invoices\InvoiceHub;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceZatcaResubmitCopy;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $business->forceFill([
        'settings' => array_merge($business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true],
        ]),
    ])->save();

    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test_key',
        'zheart.auto_submit_signed' => true,
    ]);

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    BusinessContext::set($business);
});

it('shows clearance and report labels by document type', function () {
    $standard = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-LABEL',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
    ]);

    $simplified = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'SIMP-LABEL',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
    ]);

    expect(HubInvoiceZatcaResubmitCopy::actionLabel($standard))->toBe(__('invoices.action_send_for_clearance'))
        ->and(HubInvoiceZatcaResubmitCopy::actionLabel($simplified))->toBe(__('invoices.action_send_report_zatca'));
});

it('renders resubmit on invoice view when pending', function () {
    seedDemoPaidPosOrder();
    $invoice = Invoice::query()->orderByDesc('id')->firstOrFail();
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'signing_submission_status' => 'signed_locally',
                'zheart_remote_submitted' => false,
                'zheart_reporting_status' => 'pending',
            ]),
            'signed_xml' => '<Invoice/>',
        ])->save();
    });

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice->fresh(), 'layout' => 'a4']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-testid="invoice-view-zatca-panel"', false)
        ->toContain('data-testid="invoice-toolbar-zatca-resubmit"', false)
        ->toContain(__('invoices.action_send_report_zatca'), false);
});

it('resubmits from hub without changing document number', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'icv' => 9,
                'invoice_hash' => 'hash-9',
                'qr' => 'cXI=',
                'zatca' => ['status' => 'reported'],
                'reporting_status' => 'reported',
            ],
        ], 200),
    ]);

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'SIMP-RETRY-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'icv' => 9,
        'invoice_hash' => 'hash-9',
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440099',
            'signing_submission_status' => 'signed_locally',
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]],
        ],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('resubmitInvoice', $invoice->id);

    $fresh = $invoice->fresh();
    expect($fresh->document_number)->toBe('SIMP-RETRY-1')
        ->and(HubInvoiceRowActions::canResendZatca($fresh))->toBeFalse();

    Http::assertSentCount(1);
});

it('does not call z-heart when resubmitting already cleared invoice', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-CLEARED',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'cleared',
            'zheart_zatca' => ['status' => 'cleared'],
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]],
        ],
    ]);

    Http::fake();

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);

    expect($result->code)->toBe('already_success');
    Http::assertNothingSent();
});

it('allows credit note clearance retry when not cleared', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'CN-RETRY',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'signing_submission_status' => 'signed_locally',
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]],
        ],
    ]);

    expect(HubInvoiceZatcaResubmitCopy::actionLabel($invoice))->toBe(__('invoices.action_send_for_clearance'))
        ->and(HubInvoiceRowActions::canResendZatca($invoice))->toBeTrue();
});