<?php

use App\Models\BusinessSetting;
use App\Support\BusinessSettingDisplayName;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
});

it('renders 404 copy in the session locale instead of always Bangla', function () {
    $enHeading = trans('errors.not_found_heading', [], 'en');
    $bnHeading = trans('errors.not_found_heading', [], 'bn');

    $html = $this->withSession(['locale' => 'en'])
        ->withUnencryptedCookie(\App\Support\LocaleCookie::NAME, 'en')
        ->get('/this-route-does-not-exist-xyz')
        ->assertNotFound()
        ->getContent();

    expect($html)
        ->toContain($enHeading)
        ->toContain('lang="en"')
        ->not->toContain($bnHeading);
});

it('uses a translated heading and business name in error page titles', function () {
    app()->setLocale('en');
    $name = BusinessSettingDisplayName::for($this->business, 'en');

    $this->get('/this-route-does-not-exist-xyz')
        ->assertNotFound()
        ->assertSee('<title>', false)
        ->assertSee(__('errors.not_found_heading').' | '.$name, false)
        ->assertDontSee('<title>'.config('app.name').'</title>', false);
});

it('titles session-expired pages with a translated heading and business name', function () {
    app()->setLocale('bn');
    $name = BusinessSettingDisplayName::for($this->business, 'bn');

    $html = view('errors.419', [
        'code' => '419',
        'heading' => __('errors.session_heading'),
        'message' => __('errors.session_lead'),
    ])->render();

    expect($html)->toContain(__('errors.session_heading').' | '.$name)
        ->and($html)->not->toContain('<title>'.config('app.name').'</title>');
});
