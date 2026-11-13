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
    $this->order = seedDemoPaidPosOrder();
});

it('lists issued invoices for owner', function () {
    $invoice = Invoice::query()->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSee($invoice->document_number, false);
});

it('denies cashier', function () {
    expect($this->cashier->can('invoices.issue'))->toBeFalse();
});

it('redirects print to receipt for pos orders', function () {
    $invoice = Invoice::query()->firstOrFail();
    $order = PosOrder::query()->findOrFail($invoice->source_id);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [$invoice]))
        ->assertRedirect(route('admin.pos.receipt', [
            'order' => $order,
            'locale' => 'ar',
        ]));
});
