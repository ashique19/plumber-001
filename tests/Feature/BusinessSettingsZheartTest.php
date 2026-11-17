<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Invoicing\ZHeartCredentials;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
    config([
        'zheart.base_url' => '',
        'zheart.api_key' => '',
    ]);
});

it('shows zheart tab only to owners', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->assertSee(__('business.sections.zheart'), false);

    Livewire::actingAs($this->cashier, 'staff')
        ->test(BusinessSettings::class)
        ->assertDontSee(__('business.sections.zheart'), false);
});

it('saves encrypted credentials and masks stored key', function () {
  $key = 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789';

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'zheart')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', $key)
        ->call('saveZheart')
        ->assertHasNoErrors();

    $this->business->refresh();
    expect(ZHeartCredentials::baseUrl($this->business))->toBe('https://z-heart.example.test')
        ->and(ZHeartCredentials::apiKey($this->business))->toBe($key)
        ->and(ZHeartCredentials::storedKeyLast4($this->business))->toBe('6789');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'zheart')
        ->assertSeeHtml('data-testid="business-zheart-api-key"')
        ->assertSee('6789', false)
        ->assertDontSee($key, false);
});

it('tests connection without leaking the api key in http payload', function () {
    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response(['status' => 'ok'], 200),
    ]);

    $key = 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789';

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'zheart')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', $key)
        ->call('testZheartConnection')
        ->assertSet('zheartTestStatus', 'ok');

    Http::assertSent(function ($request) use ($key) {
        return $request->hasHeader('Authorization', 'Bearer '.$key)
            && ! str_contains((string) $request->url(), $key);
    });
});

it('uses database credentials instead of env when signing client resolves key', function () {
    config([
        'zheart.base_url' => 'https://env.example.test',
        'zheart.api_key' => 'zh_envonly.ABCDEF',
    ]);

    $settings = $this->business->settings ?? BusinessSetting::defaultSettings();
    $settings = ZHeartCredentials::applyKeyToSettings($settings, 'zh_dbprefx.abcdefghi');
    $settings['zheart']['base_url'] = 'https://db.example.test';
    $this->business->writeSettings($settings);

    expect(ZHeartCredentials::baseUrl($this->business))->toBe('https://db.example.test')
        ->and(ZHeartCredentials::apiKey($this->business))->toBe('zh_dbprefx.abcdefghi');
});