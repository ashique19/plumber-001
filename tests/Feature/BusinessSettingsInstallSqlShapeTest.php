<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\BusinessProfile;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

function businessSettingsLikeInstallSqlImport(BusinessSetting $business): BusinessSetting
{
    $business->forceFill([
        'settings' => [
            'theme' => [
                'layout_key' => 'plumber',
            ],
            'default_locale' => 'bn',
        ],
    ])->save();

    return $business->fresh();
}

it('always offers brand color presets when theme colors are missing from the database', function () {
    $business = businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    expect(BusinessProfile::brandColorOptions($business))->toHaveCount(4)
        ->and(array_keys(BusinessProfile::brandColorOptions($business)))->toContain('navy');

    $html = Livewire::test(BusinessSettings::class)->html();

    expect($html)->toContain('value="navy"')
        ->and($html)->toContain('value="steel"');
});

it('saves the brand tab for install.sql-shaped settings without theme colors', function () {
    $business = businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', 'Pipe Works LLC')
        ->set('brandColorKey', 'navy')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $fresh = $business->fresh();

    expect(data_get($fresh->settings, 'business.brand.display_name_en'))->toBe('Pipe Works LLC')
        ->and(data_get($fresh->settings, 'theme.colors.navy'))->toBe('#0B1F3A');
});

it('defaults an invalid brand color when saving brand', function () {
    businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', 'Pipe Works LLC')
        ->set('brandColorKey', 'not-a-preset')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertSet('brandColorKey', 'navy')
        ->assertDispatched('toast');
});

it('saves the legal tab independently of brand fields on install.sql-shaped settings', function () {
    $business = businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('displayNameEn', '')
        ->set('brandColorKey', '')
        ->set('crNumber', '1010999888')
        ->set('vatNumber', '')
        ->set('addressAr', '')
        ->set('naStreetAr', 'شارع الأمير')
        ->set('naCityAr', 'الرياض')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $legal = data_get($business->fresh()->settings, 'business.legal');

    expect($legal['cr_number'])->toBe('1010999888')
        ->and($legal['address_ar'])->toBe('')
        ->and(data_get($legal, 'national_address.street_ar'))->toBe('شارع الأمير');
});

it('surfaces legal validation errors with toast instead of failing silently', function () {
    businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('vatNumber', '123')
        ->call('saveLegal')
        ->assertHasErrors(['vatNumber'])
        ->assertDispatched('toast')
        ->assertSet('tabsWithErrors', ['legal']);
});

it('surfaces brand validation errors with toast', function () {
    businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->call('saveBrand')
        ->assertHasErrors(['displayNameEn'])
        ->assertDispatched('toast')
        ->assertSet('tabsWithErrors', ['brand']);
});

it('saves contact hours invoicing and extras tabs on install.sql-shaped settings', function () {
    $business = businessSettingsLikeInstallSqlImport(BusinessSetting::singleton());

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966 50 111 2233')
        ->call('saveContact')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('paymentTermsDays', 14)
        ->call('saveInvoicing')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'hours')
        ->set('hoursNote', 'Fri afternoon only')
        ->call('saveHours')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'extras')
        ->set('plumberLicense', 'PLB-77')
        ->call('saveExtras')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $fresh = $business->fresh();

    expect(data_get($fresh->settings, 'business.contact.phone'))->toBe('+966 50 111 2233')
        ->and(data_get($fresh->settings, 'invoicing.payment_terms_days'))->toBe(14)
        ->and(data_get($fresh->settings, 'business.hours.note'))->toBe('Fri afternoon only')
        ->and(data_get($fresh->settings, 'business.theme_extras.license_number'))->toBe('PLB-77');
});