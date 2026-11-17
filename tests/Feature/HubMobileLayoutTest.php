<?php

use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Payments\PaymentsDuesHub;
use App\Livewire\Quotations\QuotationHub;
use App\Livewire\Reports\OwnerAnalytics;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders quotations hub fab as a link to create', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->html();

    expect($html)
        ->toContain('data-testid="admin-hub-fab"')
        ->toContain('href="'.route('admin.quotations.create').'"');
});

it('keeps invoice resend in the sticky invoice-number cell', function () {
    $css = (string) file_get_contents(resource_path('css/hub-document-table.css'));

    expect($css)
        ->toContain('.hub-doc-table__resend-inline')
        ->toContain('z-index: 4')
        ->toContain('z-index: 1');

    $blade = (string) file_get_contents(resource_path('views/livewire/invoices/invoice-hub.blade.php'));
    expect($blade)
        ->toContain('hub-doc-table__id-cell')
        ->toContain('hub-doc-table__resend-inline');
});

it('stacks dues customer balances on mobile and keeps a scrollable table', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(PaymentsDuesHub::class)
        ->html();

    expect($html)->toContain(__('payments.outstanding_customers'));

    $blade = (string) file_get_contents(resource_path('views/livewire/payments/payments-dues-hub.blade.php'));
    expect($blade)
        ->toContain('data-testid="dues-customer-cards"')
        ->toContain('data-testid="dues-customer-table"');
});

it('wraps customer report dues table for in-card scroll', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(OwnerAnalytics::class)
        ->set('section', 'customers')
        ->html();

    expect($html)->toContain('data-testid="reports-customers-table"');
});
