<?php

use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Quotations\QuotationHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Business\BusinessContext;
use App\Support\Hub\HubInvoicePrintLinks;
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
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();

    BusinessContext::set($this->tenant);
    seedDemoPaidPosOrder();
});

it('renders invoice hub table with print links', function () {
    $invoice = Invoice::query()->firstOrFail();
    $urls = HubInvoicePrintLinks::urls($invoice);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSeeHtml('hub-doc-table')
        ->assertSeeHtml('hub-doc-table__sticky')
        ->assertSee($invoice->document_number, false)
        ->assertSeeHtml('href="'.e($urls['print']).'"', false)
        ->assertSeeHtml('autoprint=1', false);
});

it('filters invoices by document number search', function () {
    $invoice = Invoice::query()->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', mb_substr($invoice->document_number, 0, 8))
        ->assertSee($invoice->document_number, false);
});

it('renders quotation hub table when quotations exist', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-TABLE-1',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'buyer_name' => 'Table Test Buyer',
            'lines' => [
                [
                    'name' => 'Line',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 100,
                    'vat_rate' => 15,
                ],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->assertSeeHtml('hub-doc-table')
        ->assertSee($quotation->quotation_number, false)
        ->assertSee('Table Test Buyer', false)
        ->assertSeeHtml('autoprint=1', false);
});