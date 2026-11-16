<?php

use App\Models\Invoice;
use Database\Seeders\DemoStaffSeeder;
use App\Business\BusinessContext;
use App\Models\StaffUser;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(\App\Models\BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    seedDemoPaidPosOrder();
});

it('renders a4 layout with document type title for pos-derived invoices', function () {
    $invoice = Invoice::query()->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))
        ->assertOk()
        ->assertSee(__('pos.receipt_title_simplified_en'), false)
        ->assertSee(__('pos.receipt_title_simplified_ar'), false);
});

it('accepts transfer payment method on pos', function () {
    expect(\App\Enums\PosPaymentMethod::Transfer->value)->toBe('transfer');
});