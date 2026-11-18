<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

afterEach(function () {
    Auth::guard('staff')->logout();
});

it('renders admin print preview shell on invoice hub with modal triggers', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'SIMP-PRINT-MODAL',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['buyer_name' => 'Buyer', 'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->html();

    expect($html)
        ->toContain('data-admin-print-open')
        ->toContain('data-print-src="'.e(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash'])).'"');

    $page = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices'))
        ->getContent();

    expect($page)->toContain('data-testid="admin-print-preview"');
});

it('does not render debug zatca metadata on issued invoice view', function () {
    seedDemoPaidPosOrder();
    $invoice = Invoice::query()->orderByDesc('id')->firstOrFail();

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-testid="invoice-view-status"', false)
        ->not->toContain(__('invoices.zatca_view_last_status'), false)
        ->not->toContain(__('invoices.zatca_view_request_id'), false)
        ->not->toContain(__('invoices.zatca_view_zatca_http_status'), false);
});

it('hides screen toolbar on embedded invoice print and keeps it on direct visit', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'STD-PRINT-EMBED',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['buyer_name' => 'Buyer', 'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $this->actingAs($this->owner, 'staff');

    $direct = (string) $this->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))->getContent();
    $embedded = (string) $this->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'embed' => 1]))->getContent();

    expect($direct)->toContain('data-testid="print-screen-toolbar"')
        ->toContain('data-testid="print-screen-back"')
        ->and($embedded)->not->toContain('data-testid="print-screen-toolbar"');
});