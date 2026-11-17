<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Quotations\QuotationHub;
use App\Models\BusinessSetting;
use App\Models\Customer;
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
});

it('paginates the invoice hub with compact pager controls', function () {
    Invoice::query()->delete();

    for ($i = 1; $i <= 25; $i++) {
        Invoice::query()->create([
            'document_number' => sprintf('INV-PAG-%03d', $i),
            'document_type' => InvoiceDocumentType::Simplified,
            'status' => InvoiceStatus::Draft,
            'payload' => ['buyer_name' => "Buyer {$i}", 'lines' => []],
        ]);
    }

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSeeHtml('data-testid="admin-hub-pager"')
        ->assertSee(__('pagination.page_x_of_y', ['current' => 1, 'last' => 2]))
        ->assertSee('INV-PAG-025', false)
        ->assertDontSee('INV-PAG-001', false)
        ->call('nextPage')
        ->assertSee(__('pagination.page_x_of_y', ['current' => 2, 'last' => 2]))
        ->assertSee('INV-PAG-001', false)
        ->assertDontSee('INV-PAG-025', false);
});

it('resets to page one when invoice filters change', function () {
    Invoice::query()->delete();

    for ($i = 1; $i <= 25; $i++) {
        Invoice::query()->create([
            'document_number' => sprintf('INV-FLT-%03d', $i),
            'document_type' => InvoiceDocumentType::Simplified,
            'status' => InvoiceStatus::Draft,
            'payload' => ['buyer_name' => "Buyer {$i}", 'lines' => []],
        ]);
    }

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('nextPage')
        ->assertSee(__('pagination.page_x_of_y', ['current' => 2, 'last' => 2]))
        ->set('search', 'INV-FLT-001')
        ->assertSee(__('pagination.page_x_of_y', ['current' => 1, 'last' => 1]))
        ->assertSee('INV-FLT-001', false);
});

it('honours per-page selection on quotation and customer hubs', function () {
    Quotation::query()->delete();
    Customer::query()->delete();

    for ($i = 1; $i <= 15; $i++) {
        Quotation::query()->create([
            'branch_id' => $this->owner->branch_id,
            'quotation_number' => sprintf('Q-PAG-%03d', $i),
            'invoice_mode' => 'simplified',
            'validity_until' => now()->addDays(7)->toDateString(),
            'payload' => [
                'buyer_name' => "Quote buyer {$i}",
                'lines' => [],
            ],
            'created_by_staff_user_id' => $this->owner->id,
        ]);
        Customer::query()->create([
            'name' => sprintf('Customer Pag %03d', $i),
            'phone' => sprintf('050000%04d', $i),
        ]);
    }

    Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->set('perPage', 10)
        ->assertSee(__('pagination.page_x_of_y', ['current' => 1, 'last' => 2]))
        ->assertSee('Q-PAG-015', false)
        ->assertDontSee('Q-PAG-001', false);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->set('perPage', 10)
        ->assertSee(__('pagination.page_x_of_y', ['current' => 1, 'last' => 2]))
        ->assertSee('Customer Pag 010', false)
        ->assertDontSee('Customer Pag 011', false);
});

it('translates compact pager strings in Arabic', function () {
    app()->setLocale('ar');

    Invoice::query()->delete();
    for ($i = 1; $i <= 25; $i++) {
        Invoice::query()->create([
            'document_number' => sprintf('INV-AR-%03d', $i),
            'document_type' => InvoiceDocumentType::Simplified,
            'status' => InvoiceStatus::Draft,
            'payload' => ['buyer_name' => "Buyer {$i}", 'lines' => []],
        ]);
    }

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['locale' => 'ar'])
        ->test(InvoiceHub::class)
        ->assertSee(__('pagination.previous', locale: 'ar'))
        ->assertSee(__('pagination.next', locale: 'ar'))
        ->assertSeeHtml('dir="rtl"');
});