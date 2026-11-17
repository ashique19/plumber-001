<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Quotations\QuotationHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
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
    seedDemoPaidPosOrder();
});

it('renders icon-only invoice row actions with aria labels', function () {
    $invoice = Invoice::query()->firstOrFail();

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->html();

    expect($html)
        ->toContain('data-testid="hub-invoice-row-actions"')
        ->toContain('hub-doc-table__action--icon')
        ->toContain('aria-label="'.e(__('invoices.action_view')).'"')
        ->toContain('data-testid="hub-row-action-view"')
        ->toContain('data-testid="hub-invoice-cards"')
        ->toContain('<span class="sr-only">'.__('invoices.action_download_pdf').'</span>');
});

it('marks disabled pdf and print icons when awaiting zatca clearance', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'STD-ICON-DIS-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'buyer_name' => 'Icon Disabled Buyer',
            'lines' => [],
            'signing_submission_status' => 'signed_locally',
            'zheart_reporting_status' => 'pending',
        ],
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->html();

    expect($html)
        ->toContain('data-testid="hub-row-action-download-disabled"')
        ->toContain('data-testid="hub-row-action-print-disabled"')
        ->toContain('aria-disabled="true"')
        ->toContain('title="'.e(__('invoices.pdf_available_after_clearance')).'"');
});

it('renders compact quotation actions with a more menu for secondary items', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-ICON-1',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => ['buyer_name' => 'Quo Icon', 'lines' => []],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->set('search', $quotation->quotation_number)
        ->html();

    expect($html)
        ->toContain('data-testid="hub-quotation-row-actions"')
        ->toContain('data-testid="hub-row-action-print"')
        ->toContain('data-testid="hub-row-action-more"')
        ->toContain('data-testid="hub-quotation-cards"');
});