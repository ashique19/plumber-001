<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Tests\Support\TranslationLeakScanner;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
    \App\Business\BusinessContext::set($this->business);
    $this->actingAs($this->owner, 'staff');
});

it('renders admin dashboard without raw translation keys in en bn and ar', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        $html = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()->getContent();

        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($html, "dashboard [{$locale}]");
        TranslationLeakScanner::assertLocaleHasNoEnglishSigningService($html, $locale, "dashboard [{$locale}]");
    }
});

it('shows translated sidebar nav labels in bn on dashboard', function () {
    $html = $this->get(route('admin.dashboard', ['locale' => 'bn']))->assertOk()->getContent();

    expect($html)->toContain(__('pos.nav_pos', [], 'bn'))
        ->and($html)->toContain(__('business.nav', [], 'bn'))
        ->and($html)->toContain(__('catalog.nav', [], 'bn'))
        ->and($html)->toContain(__('inventory.nav', [], 'bn'));
});