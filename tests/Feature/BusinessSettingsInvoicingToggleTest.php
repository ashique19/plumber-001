<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
});

it('persists invoicing integration toggle per tab save', function () {
    $this->business->forceFill([
        'settings' => array_merge($this->business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true, 'default_document_type' => 'simplified'],
        ]),
    ])->save();

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('invoicingIntegrationEnabled', false)
        ->call('saveInvoicing')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($this->business->fresh()->invoicingIntegrationEnabled())->toBeFalse();
});
