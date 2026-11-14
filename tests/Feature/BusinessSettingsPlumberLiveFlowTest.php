<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\BusinessSettingDisplayName;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');

    shapeBusinessLikeInstallSqlImport(BusinessSetting::primary());
});

/**
 * Minimal settings JSON as shipped in install.sql (+ legal_entity), before ensureHydrated expands it.
 */
function shapeBusinessLikeInstallSqlImport(BusinessSetting $business): void
{
    $business->forceFill([
        'name' => 'Demo Plumbing Co',
        'settings' => [
            'theme' => ['layout_key' => 'plumber'],
            'default_locale' => 'bn',
            'legal_entity' => [
                'name_en' => 'Demo Plumbing Co',
                'name_ar' => '',
                'vat_number' => '',
                'cr_number' => '',
                'address' => '',
            ],
        ],
    ])->save();
}

it('persists brand save for plumber install.sql shape with bn locale', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', 'Dhaka Plumbing Co')
        ->set('displayNameBn', 'ঢাকা প্লাম্বিং কোং')
        ->set('displayNameAr', 'شركة السباكة')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $row = BusinessSetting::primary()->fresh();

    expect(data_get($row->settings, 'business.brand.display_name_en'))->toBe('Dhaka Plumbing Co')
        ->and(data_get($row->settings, 'business.brand.display_name_bn'))->toBe('ঢাকা প্লাম্বিং কোং')
        ->and(data_get($row->settings, 'legal_entity.name_en'))->toBe('Dhaka Plumbing Co')
        ->and($row->name)->toBe('Dhaka Plumbing Co')
        ->and(BusinessSettingDisplayName::for($row, 'bn'))->toBe('ঢাকা প্লাম্বিং কোং');

    Livewire::test(BusinessSettings::class)
        ->assertSet('displayNameBn', 'ঢাকা প্লাম্বিং কোং')
        ->assertSet('displayNameEn', 'Dhaka Plumbing Co');
});

it('persists legal tab for plumber install.sql shape with bn locale', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('legalNameAr', 'شركة أنابيب الرياض')
        ->set('crNumber', '1010998877')
        ->set('addressAr', 'الرياض، العليا')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $row = BusinessSetting::primary()->fresh();

    expect(data_get($row->settings, 'business.legal.name_ar'))->toBe('شركة أنابيب الرياض')
        ->and(data_get($row->settings, 'business.legal.cr_number'))->toBe('1010998877')
        ->and(data_get($row->settings, 'legal_entity.cr_number'))->toBe('1010998877');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->assertSet('legalNameAr', 'شركة أنابيب الرياض')
        ->assertSet('crNumber', '1010998877');
});

it('surfaces brand validation errors with toast warning', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->call('saveBrand')
        ->assertHasErrors(['displayNameEn'])
        ->assertDispatched('toast', type: 'warning');
});