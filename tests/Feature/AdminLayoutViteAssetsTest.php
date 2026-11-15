<?php

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->firstOrFail();
});

it('manifest attaches vendor css only to the app.css entry, not app.js', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    $appCss = $manifest['resources/css/app.css']['file'] ?? null;
    $appJs = $manifest['resources/js/app.js'] ?? [];

    expect($appCss)->not->toBeNull();

    expect($appJs['css'] ?? [])->toBe([]);
});

it('renders exactly one built app stylesheet on admin purchase create', function () {
    $this->actingAs($this->owner, 'staff');

    $html = (string) $this->get(route('admin.purchases.create'))->getContent();

    preg_match_all('#<link[^>]+rel="stylesheet"[^>]+href="([^"]+/build/assets/app-[^"]+\.css)"[^>]*>#', $html, $matches);

    expect($matches[1])->toBe(array_unique($matches[1]))
        ->and($matches[1])->toHaveCount(1);
});

it('renders exactly one built app stylesheet on suppliers hub', function () {
    $this->actingAs($this->owner, 'staff');

    $html = (string) $this->get(route('admin.suppliers'))->getContent();

    preg_match_all('#<link[^>]+rel="stylesheet"[^>]+href="([^"]+/build/assets/app-[^"]+\.css)"[^>]*>#', $html, $matches);

    expect($matches[1])->toHaveCount(1);
});

it('compiled app css includes typeahead leading icon padding utilities', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    $cssPath = public_path('build/'.($manifest['resources/css/app.css']['file'] ?? ''));

    expect(is_file($cssPath))->toBeTrue();

    $css = (string) file_get_contents($cssPath);

    expect($css)
        ->toContain('.ps-10{')
        ->toContain('padding-inline-start')
        ->toContain('admin-typeahead__input-wrap');
});