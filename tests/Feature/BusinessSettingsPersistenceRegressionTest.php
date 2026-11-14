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
});

it('reloads brand and legal fields after save', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('displayNameEn', 'Reload Test Co')
        ->set('displayNameAr', 'اختبار')
        ->set('displayNameBn', 'রিলোড')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->assertSet('displayNameEn', 'Reload Test Co')
        ->assertSet('displayNameAr', 'اختبار')
        ->assertSet('displayNameBn', 'রিলোড');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('vatNumber', '')
        ->set('legalNameAr', 'شركة تجريبية')
        ->set('addressAr', 'الرياض')
        ->call('saveLegal')
        ->assertHasNoErrors();

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'legal')
        ->assertSet('legalNameAr', 'شركة تجريبية')
        ->assertSet('addressAr', 'الرياض');
});