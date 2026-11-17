<?php

use App\Business\BusinessContext;
use App\Livewire\Admin\StaffUsersHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Quotations\QuotationHub;
use App\Models\BusinessSetting;
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

it('renders shared alpine hub row more menu on invoice quotation and staff hubs', function () {
    $invoiceHtml = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->html();

    expect($invoiceHtml)
        ->toContain('x-data="hubRowMoreMenu(')
        ->toContain('x-teleport="body"')
        ->toContain('wire:ignore.self')
        ->toContain('data-hub-row-more-menu="invoice-')
        ->not->toContain('<details class="hub-doc-table__more');

    $quotationHtml = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->html();

    expect($quotationHtml)
        ->toContain('data-hub-row-more-menu="quotation-')
        ->not->toContain('<details class="hub-doc-table__more');

    $usersHtml = Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->html();

    expect($usersHtml)
        ->toContain('data-hub-row-more-menu="staff-user-')
        ->not->toContain('<details class="hub-doc-table__more');
});