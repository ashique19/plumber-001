<?php

use App\Livewire\Invoices\IssueInvoice;
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

it('renders sectioned identity layout on standard quick customer modal', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('openQuickCustomerModal')
        ->assertSeeHtml('customer-identity-fields--sectioned')
        ->assertSeeHtml('customer-identity-section-identity')
        ->assertSeeHtml('customer-identity-section-contact')
        ->assertSeeHtml('customer-identity-section-address')
        ->assertSeeHtml('x-modal__footer--in-form')
        ->assertSee(__('customers.sections.identity'), false)
        ->assertSee(__('customers.sections.national_address'), false);
});