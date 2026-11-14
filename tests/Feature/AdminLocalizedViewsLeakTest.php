<?php

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Tests\Support\TranslationLeakScanner;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->actingAs($this->owner, 'staff');
});

it('renders standard invoice issue buyer labels without raw translation keys', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $html = $this->get(route('admin.invoices.issue', ['mode' => 'standard']))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($html, "issue/standard [{$locale}]");
        expect($html)->not->toContain('business.fields.fields.');
    }
});

it('renders dashboard and business settings without raw translation keys', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        $dashboard = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($dashboard, "dashboard [{$locale}]");

        $business = $this->get(route('admin.business', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($business, "business settings [{$locale}]");

        if (in_array($locale, ['bn', 'ar'], true)) {
            expect(strtolower($dashboard))->not->toContain('signing service');
        }
    }
});

it('shows translated sidebar nav labels in bn and ar', function () {
    $html = $this->get(route('admin.dashboard', ['locale' => 'bn']))->assertOk()->getContent();
    expect($html)->toContain(__('business.nav', [], 'bn'))
        ->and($html)->toContain(__('catalog.nav', [], 'bn'))
        ->and($html)->toContain(__('inventory.nav', [], 'bn'));

    $html = $this->get(route('admin.dashboard', ['locale' => 'ar']))->assertOk()->getContent();
    expect($html)->toContain(__('business.nav', [], 'ar'))
        ->and($html)->toContain(__('catalog.nav', [], 'ar'));
});