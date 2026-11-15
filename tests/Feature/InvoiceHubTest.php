<?php

use App\Livewire\Invoices\InvoiceHub;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Business\BusinessContext;
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
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@example.test')->firstOrFail();

    BusinessContext::set($this->tenant);
    session()->forget('locale');
    $this->order = seedDemoPaidPosOrder();
});

it('lists issued invoices for owner', function () {
    $invoice = Invoice::query()->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSee($invoice->document_number, false);
});

it('puts invoice search guidance in the placeholder and aligns filter fields', function () {
    app()->setLocale('bn');

    $hint = __('invoices.search_hint');

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSeeHtml('app-filter-fields')
        ->assertSeeHtml('items-end')
        ->assertSeeHtml('placeholder="'.$hint.'"', false)
        ->assertDontSeeHtml('form-field__hint');
});

it('denies cashier', function () {
    expect($this->cashier->can('invoices.issue'))->toBeFalse();
});

it('renders cash print layout by default for simplified pos invoices', function () {
    $invoice = Invoice::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [$invoice, 'layout' => 'cash']))
        ->assertOk()
        ->assertSee('Subtotal (ex VAT)', false)
        ->assertSee('المجموع (بدون ضريبة)', false)
        ->assertSee($invoice->document_number, false);
});
