<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Business\BusinessSettingsService;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->cafe = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

function legacySqlBusinessShape(BusinessSetting $business): BusinessSetting
{
    $settings = $business->settings ?? [];
    $legal = (array) ($settings['legal_entity'] ?? []);
    $plumber = (array) data_get($settings, 'content.plumber', []);
    $settings['business'] = [
        'brand' => [
            'display_name_en' => (string) ($legal['name_en'] ?? $business->name),
            'display_name_ar' => (string) ($legal['name_ar'] ?? ''),
            'brand_color_key' => 'navy',
        ],
        'legal' => [
            'name_en' => (string) ($legal['name_en'] ?? ''),
            'name_ar' => (string) ($legal['name_ar'] ?? ''),
            'vat_number' => (string) ($legal['vat_number'] ?? ''),
            'cr_number' => (string) ($legal['cr_number'] ?? ''),
            'address_en' => (string) ($legal['address'] ?? ''),
            'address_ar' => (string) ($legal['address_ar'] ?? ''),
        ],
        'contact' => [
            'phone' => (string) data_get($plumber, 'contact.phone', ''),
            'lat' => (string) data_get($plumber, 'location.lat', ''),
            'lng' => (string) data_get($plumber, 'location.lng', ''),
        ],
    ];
    $business->forceFill(['settings' => $settings])->save();

    return $business->fresh();
}

it('hydrates partial sql business contact from plumber content on mount', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->assertSet('phone', '+966 11 200 4472')
        ->assertSet('lat', '24.7136');
});

it('saves contact for sql-partial business settings without error', function () {
    $business = legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966501234567')
        ->call('saveContact')
        ->assertHasNoErrors();

    expect(data_get($business->fresh()->settings, 'business.contact.phone'))->toBe('+966501234567');
});

it('rejects invalid contact email with validation not server error', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('email', 'not-an-email')
        ->call('saveContact')
        ->assertHasErrors(['email']);
});

it('rejects shorthand email a at b', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('email', 'a@b')
        ->call('saveContact')
        ->assertHasErrors(['email']);
});

it('accepts map picker numeric lat/lng from livewire state', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('lat', 24.7136)
        ->set('lng', 46.6753)
        ->call('saveContact')
        ->assertHasNoErrors();
});

it('validates brand tab inline', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('displayNameEn', '')
        ->call('saveBrand')
        ->assertHasErrors(['displayNameEn']);
});

it('validates legal tab inline', function () {
    legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('vatNumber', '123')
        ->call('saveLegal')
        ->assertHasErrors(['vatNumber']);
});

it('saves hours tab for legacy tenant', function () {
    $business = legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'hours')
        ->set('hoursNote', 'Ramadan hours vary')
        ->call('saveHours')
        ->assertHasNoErrors();

    expect(data_get($business->fresh()->settings, 'business.hours.note'))->toBe('Ramadan hours vary');
});

it('saves extras tab for plumber business', function () {
    $business = legacySqlBusinessShape($this->cafe);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'extras')
        ->set('plumberLicense', 'PLB-1001')
        ->call('saveExtras')
        ->assertHasNoErrors();

    expect(data_get($business->fresh()->settings, 'business.theme_extras.license_number'))->toBe('PLB-1001');
});

it('returns http 200 for livewire saveContact on legacy tenant', function () {
    $business = legacySqlBusinessShape($this->cafe);
    app(BusinessSettingsService::class)->ensureHydrated($business);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->call('saveContact')
        ->assertHasNoErrors();
});

it('renders mobile section select and hours cards markup', function () {
    legacySqlBusinessShape($this->cafe);

    $html = Livewire::test(BusinessSettings::class)
        ->set('section', 'hours')
        ->html();

    expect($html)->toContain('business-hours-cards')
        ->and($html)->toContain('app-toolbar-select')
        ->and($html)->toContain('business-settings-page');
});
