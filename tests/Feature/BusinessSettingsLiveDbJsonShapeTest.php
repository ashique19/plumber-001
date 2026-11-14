<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Carbon\Carbon;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Matches live production row id=1: theme plumber + logo_url null, often before business.* exists.
 *
 * @return array<string, mixed>
 */
function liveDbBusinessSettingsJson(): array
{
    $defaults = BusinessSetting::defaultSettings();

    return [
        'theme' => [
            'layout_key' => 'plumber',
            'logo_url' => null,
            'colors' => $defaults['theme']['colors'],
            'font' => 'Instrument Sans',
            'font_ar' => 'Tajawal',
        ],
        'default_locale' => 'bn',
        'legal_entity' => [
            'name_en' => 'Demo Plumbing Co',
            'name_ar' => '',
            'vat_number' => '',
            'cr_number' => '',
            'address' => '',
            'address_ar' => '',
            'national_address' => $defaults['legal_entity']['national_address'],
        ],
        'invoicing' => $defaults['invoicing'],
        'content' => ['plumber' => []],
    ];
}

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');

    $stamp = Carbon::parse('2026-11-14 17:14:02', 'UTC');

    BusinessSetting::query()->whereKey(1)->update([
        'name' => 'Demo Plumbing Co',
        'settings' => json_encode(liveDbBusinessSettingsJson(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'updated_at' => $stamp,
    ]);

    expect(BusinessSetting::query()->count())->toBe(1);
});

it('bumps business_settings.updated_at when saveBrand runs on live json shape', function () {
    app()->setLocale('bn');

    Carbon::setTestNow('2026-11-14 17:14:02', 'UTC');

    $component = Livewire::test(BusinessSettings::class);

    Carbon::setTestNow('2026-11-14 17:55:00', 'UTC');

    $component
        ->set('displayNameBn', 'লাইভ ডাটাবেস নাম')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    $row = BusinessSetting::primary()->fresh();

    expect($row->updated_at->equalTo(Carbon::parse('2026-11-14 17:55:00', 'UTC')))->toBeTrue()
        ->and(data_get($row->settings, 'business.brand.display_name_bn'))->toBe('লাইভ ডাটাবেস নাম')
        ->and($row->id)->toBe(1);

    Carbon::setTestNow();
});

it('invokes saveBrand and persists when only bangla name is edited on live json shape', function () {
    app()->setLocale('bn');
    Illuminate\Support\Facades\Log::spy();

    $component = Livewire::test(BusinessSettings::class);

    $component
        ->set('displayNameEn', '')
        ->set('displayNameBn', 'শুধু বাংলা')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->with('business.settings.save_invoked', ['section' => 'brand', 'business_id' => 1]);

    Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->with('business.settings.saved', ['section' => 'brand', 'business_id' => 1]);

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'business.brand.display_name_bn'))
        ->toBe('শুধু বাংলা');
});

it('invokes saveLegal and touches row on live json shape', function () {
    app()->setLocale('bn');

    Carbon::setTestNow('2026-11-14 17:14:02', 'UTC');

    $component = Livewire::test(BusinessSettings::class);

    Carbon::setTestNow('2026-11-14 17:55:00', 'UTC');

    $component
        ->set('section', 'legal')
        ->set('legalNameAr', 'شركة السباكة المحدثة')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    $row = BusinessSetting::primary()->fresh();

    expect($row->updated_at->equalTo(Carbon::parse('2026-11-14 17:55:00', 'UTC')))->toBeTrue()
        ->and(data_get($row->settings, 'business.legal.name_ar'))->toBe('شركة السباكة المحدثة');

    Carbon::setTestNow();
});

it('uses only business_settings id 1 primary row', function () {
    app()->setLocale('bn');

    Livewire::test(BusinessSettings::class)
        ->set('displayNameBn', 'রো টেস্ট')
        ->call('saveBrand')
        ->assertHasNoErrors();

    $ids = DB::table('business_settings')->pluck('id')->all();
    expect($ids)->toBe([1]);
});