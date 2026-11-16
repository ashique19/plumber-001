<?php

use App\Livewire\Customers\CustomerHub;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\StaffUser;
use App\Support\Customers\CustomerSchema;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);

    foreach ([
        'legal_name_ar',
        'legal_name_en',
        'vat_registered',
        'alt_id_type',
        'alt_id_number',
        'country_code',
        'street_name',
        'city',
    ] as $column) {
        if (Schema::hasColumn('customers', $column)) {
            Schema::table('customers', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    CustomerSchema::resetCache();
    expect(CustomerSchema::hasIdentityColumns())->toBeFalse();
});

it('sorts searches and opens edit on legacy customers table without zatca columns', function () {
    $customer = Customer::query()->where('phone', '0501000001')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertSeeHtml('data-testid="customers-table"')
        ->call('sortBy', 'phone')
        ->assertHasNoErrors()
        ->set('search', 'Regular')
        ->assertHasNoErrors()
        ->call('startEdit', $customer->id)
        ->assertSet('showCustomerModal', true)
        ->assertHasNoErrors();
});
