<?php

use App\Models\BusinessSetting;
use App\Business\BusinessContext;
use Database\Seeders\OwnerAdminSeeder;

beforeEach(function () {
    (new OwnerAdminSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
});

afterEach(function () {
    BusinessContext::clear();
});

it('loads plumber themed business settings', function () {
    expect($this->business->isPlumberTheme())->toBeTrue()
        ->and($this->business->displayName('ar'))->not->toBe('');
});

it('does not expose a public themed storefront', function () {
    expect($this->business->hasThemedStorefront())->toBeFalse();
});
