<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\BusinessHours;
use App\Support\BusinessProfile;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

it('saves contact without legal national address fields filled', function () {
    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('naBuilding', '')
        ->set('phone', '+966 11 200 9999')
        ->call('saveContact')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(data_get($this->business->fresh()->settings, 'business.contact.phone'))->toBe('+966 11 200 9999');
});

it('rejects shorthand contact email with inline error', function () {
    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('email', 'a@b')
        ->call('saveContact')
        ->assertHasErrors(['email']);
});

it('hydrates admin hours from storefront legacy rows', function () {
    $component = Livewire::test(BusinessSettings::class)->set('section', 'hours');
    $weekdays = $component->get('weekdays');
    expect($weekdays)->toHaveCount(7)
        ->and($weekdays[0]['open'])->not->toBe('')
        ->and($weekdays[0]['close'])->not->toBe('');
});

it('mirrors plumber business hours into content after save', function () {
    $weekdays = BusinessProfile::normalizeWeekdays(BusinessProfile::defaultWeekdays());
    foreach ($weekdays as &$day) {
        $day['closed'] = false;
        $day['open'] = '06:30';
        $day['close'] = '22:30';
    }
    unset($day);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'hours')
        ->set('weekdays', $weekdays)
        ->call('saveHours');

    $rowText = BusinessHours::toLegacyRows($weekdays)[0]['value_en'] ?? '';
    $stored = data_get($this->business->fresh()->settings, 'content.plumber.hours.0.value_en');
    expect($stored)->toBe($rowText);
});
