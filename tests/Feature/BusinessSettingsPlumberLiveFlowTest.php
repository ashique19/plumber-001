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

it('persists only the editable legal-tab fields for plumber install.sql shape with bn locale', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('legalNameAr', 'شركة أنابيب الرياض')
        ->set('crNumber', '1010998877')
        ->set('legalEmail', 'office@pipes.test')
        ->set('naDistrictAr', 'العليا')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $row = BusinessSetting::primary()->fresh();

    expect(data_get($row->settings, 'business.legal.name_ar'))->not->toBe('شركة أنابيب الرياض')
        ->and(data_get($row->settings, 'legal_entity.cr_number'))->not->toBe('1010998877')
        ->and(data_get($row->settings, 'legal_entity.email'))->toBe('office@pipes.test')
        ->and(data_get($row->settings, 'legal_entity.national_address.district_ar'))->toBe('العليا');

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->assertSet('legalEmail', 'office@pipes.test')
        ->assertSet('naDistrictAr', 'العليا')
        ->assertSee('Z-Heart');
});

it('surfaces brand validation errors with toast warning', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->set('displayNameBn', '')
        ->call('saveBrand')
        ->assertHasErrors(['displayNameEn', 'displayNameBn'])
        ->assertDispatched('toast', type: 'warning');
});

it('allows brand save with bangla name only on plumber install shape', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->set('displayNameBn', 'শুধু বাংলা নাম')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'business.brand.display_name_bn'))
        ->toBe('শুধু বাংলা নাম');
});

it('does not block legal save when legacy postal in json is invalid', function () {
    $business = BusinessSetting::primary();
    $settings = $business->settings;
    data_set($settings, 'business.legal.national_address.postal_code', '1234');
    $business->forceFill(['settings' => $settings])->save();

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('legalMobile', '+966500000002')
        ->call('saveLegal')
        ->assertHasNoErrors();

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'legal_entity.mobile'))
        ->toBe('+966500000002');
});

it('logs validation failures without throwing server errors', function () {
    Illuminate\Support\Facades\Log::spy();
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->set('displayNameBn', '')
        ->call('saveBrand');

    Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->with('business.settings.validation_failed', \Mockery::on(function (array $context): bool {
            return ($context['section'] ?? '') === 'brand'
                && is_array($context['fields'] ?? null)
                && $context['fields'] !== [];
        }));
});