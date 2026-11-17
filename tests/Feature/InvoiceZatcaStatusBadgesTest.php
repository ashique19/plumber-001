<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceStatusDisplay;
use App\Support\Hub\HubInvoiceTableRow;
use App\Support\Hub\HubInvoiceZatcaBadge;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
    BusinessContext::clear();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

function makeStandardAwaitingClearanceInvoice(): Invoice
{
    $invoice = Invoice::query()->create([
        'document_number' => 'STD-ZATCA-BADGE-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'buyer_name' => 'Badge Test Buyer',
            'lines' => [
                [
                    'name' => 'Line',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 100,
                    'vat_rate' => 15,
                ],
            ],
            'signing_submission_status' => 'signed_locally',
            'zheart_reporting_status' => 'pending',
            'zheart_remote_submitted' => false,
        ],
    ]);

    expect(HubInvoiceZatcaBadge::badgeKey($invoice))->toBe(HubInvoiceZatcaBadge::ClearancePending)
        ->and(HubInvoiceTableRow::invoiceStatusKey($invoice))->toBe('issued')
        ->and(HubInvoiceStatusDisplay::awaitingStandardClearance($invoice))->toBeTrue();

    return $invoice;
}

it('shows issued lifecycle and clearance pending zatca as separate hub pills', function () {
    $invoice = makeStandardAwaitingClearanceInvoice();
    $clearance = __('invoices.zatca_badge.clearance_pending');
    $issued = __('invoices.invoice_status.issued');
    $hint = __('invoices.zatca_clearance_hint');
    $pdfBlocked = __('invoices.pdf_available_after_clearance');

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->assertSee($issued, false)
        ->assertSee($clearance, false)
        ->assertSeeHtml('title="'.e($pdfBlocked).'"', false)
        ->assertSee($hint, false)
        ->assertSeeHtml('data-testid="hub-invoice-status-pill-invoice"', false)
        ->assertSeeHtml('data-testid="hub-invoice-status-pill-zatca"', false)
        ->assertSeeHtml('hub-invoice-status-pill--invoice', false)
        ->assertSeeHtml('hub-invoice-status-pill--zatca', false);
});

it('shows awaiting clearance status block on standard invoice print view', function () {
    $invoice = makeStandardAwaitingClearanceInvoice();

    $response = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']));

    $response->assertOk()
        ->assertSee(__('invoices.invoice_status.issued'), false)
        ->assertSee(__('invoices.zatca_badge.clearance_pending'), false)
        ->assertSee(__('invoices.zatca_clearance_hint'), false)
        ->assertSeeHtml('data-testid="invoice-view-status"', false);
});

it('labels simplified reporting pending on zatca only', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'SIM-ZATCA-PENDING-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [],
            'signing_submission_status' => 'signed_locally',
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
        ],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->assertSee(__('invoices.invoice_status.issued'), false)
        ->assertSee(__('invoices.zatca_badge.reporting_pending'), false)
        ->assertDontSee(__('invoices.zatca_clearance_hint'), false);
});

it('maps credit notes to credited lifecycle status', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'CN-1',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'payload' => ['lines' => []],
    ]);

    expect(HubInvoiceTableRow::invoiceStatusKey($invoice))->toBe('credited');
});