<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceListLabel;
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

it('shows lifecycle tabs and filters draft invoices', function () {
    $draft = Invoice::query()->create([
        'document_number' => 'SIMP-20261117-201629',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['buyer_name' => 'Draft Nav Buyer', 'lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
        'created_at' => now()->subHour(),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSeeHtml('data-testid="hub-invoice-lifecycle-tabs"')
        ->set('lifecycleTab', 'drafts')
        ->assertSet('lifecycleTab', 'drafts')
        ->assertSee(HubInvoiceListLabel::forHub($draft), false)
        ->assertDontSee(Invoice::query()->firstOrFail()->document_number, false);
});

it('renders draft row tools and payment dash', function () {
    $draft = Invoice::query()->create([
        'document_number' => 'SIMP-20261117-202222',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => [
            'buyer_name' => 'Draft Tools',
            'lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15]],
        ],
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('lifecycleTab', 'drafts')
        ->set('search', 'Draft Tools')
        ->html();

    expect($html)
        ->toContain('data-testid="hub-row-action-edit"')
        ->toContain('data-testid="hub-row-action-preview-draft"')
        ->toContain('data-testid="hub-row-action-more"')
        ->toContain('data-testid="hub-invoice-mobile-row-link"');
});

it('links dashboard drafts tile to the drafts lifecycle tab', function () {
    Invoice::query()->create([
        'document_number' => 'SIMP-20261117-203333',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['buyer_name' => 'Dash Draft', 'lines' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.tiles.invoice_drafts', ['count' => 1]), false)
        ->assertSeeHtml('lifecycle=drafts');
});
