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

it('shows one z-heart tab with connection block for owners only', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->assertSeeHtml('data-testid="business-zheart-form"')
        ->html();

    expect($html)->not->toContain(__('business.sections.zheart'))
        ->and(substr_count($html, 'wire:click="setSection(\'invoicing\')"'))->toBe(1);

    $manager = StaffUser::query()->firstOrCreate(
        ['email' => 'manager-zheart-tab@example.test'],
        [
            'name' => 'Manager',
            'password' => bcrypt('TestPassword!2026'),
            'is_active' => true,
        ],
    );
    $manager->syncRoles(['manager']);

    Livewire::actingAs($manager, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->assertDontSeeHtml('data-testid="business-zheart-form"');
});

it('prefills base url from env when database is empty', function () {
    config([
        'zheart.base_url' => 'https://env-zheart.example.test',
        'zheart.api_key' => 'zh_envonly.ABCDEF',
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->assertSet('zheartBaseUrl', 'https://env-zheart.example.test')
        ->assertSet('zheartBaseUrlFromEnv', true)
        ->assertSeeHtml('data-testid="business-zheart-base-env-hint"')
        ->assertSee(__('business.zheart.key_status_env', ['last4' => 'CDEF']), false);
});

it('saves encrypted credentials and masks stored key', function () {
    $key = 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789';

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
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
        ->set('section', 'invoicing')
        ->assertSeeHtml('data-testid="business-zheart-api-key"')
        ->assertSee(__('business.zheart.key_status_saved', ['last4' => '6789']), false)
        ->assertDontSee($key, false);
});

it('tests connection without leaking the api key in http payload', function () {
    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response([
            'status' => 'ok',
            'client' => [
                'name' => 'Test Client',
                'vat_number' => '300012345600003',
                'cr_number' => '1010123456',
                'environment' => 'sandbox',
                'active' => true,
                'onboarded' => true,
            ],
            'api_key' => ['label' => 'Sandbox key', 'last4' => '6789'],
            'ip_allowlist_enabled' => true,
        ], 200),
    ]);

    $key = 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789';

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', $key)
        ->call('testZheartConnection')
        ->assertSet('zheartTestStatus', 'ok')
        ->assertSet('zheartTestHttpStatus', 200)
        ->assertSeeHtml('data-testid="business-zheart-test-card"')
        ->assertSee('Test Client', false)
        ->assertSee('300012345600003', false)
        ->assertSeeHtml('data-testid="business-zheart-test-http-status"');

    Http::assertSent(function ($request) use ($key) {
        return $request->hasHeader('Authorization', 'Bearer '.$key)
            && ! str_contains((string) $request->url(), $key);
    });
});

it('shows vat mismatch warning when z-heart client vat differs from business legal', function () {
    $settings = $this->business->settings ?? BusinessSetting::defaultSettings();
    $settings['legal']['vat_number'] = '300012345600003';
    $this->business->writeSettings($settings);

    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response([
            'status' => 'ok',
            'client' => [
                'name' => 'Test Client',
                'vat_number' => '300099999900003',
                'environment' => 'sandbox',
            ],
        ], 200),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789')
        ->call('testZheartConnection')
        ->assertSeeHtml('data-testid="business-zheart-test-warnings"')
        ->assertSee('300099999900003', false);
});

it('shows warning when auth endpoint returns 404', function () {
    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response([], 404),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789')
        ->call('testZheartConnection')
        ->assertSet('zheartTestStatus', 'warn')
        ->assertSet('zheartTestHttpStatus', 404)
        ->assertSet('zheartTestReason', 'outdated')
        ->assertSee(__('business.zheart.test_reason_outdated'), false);
});

it('maps legacy zheart section to invoicing tab', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->call('setSection', 'zheart')
        ->assertSet('section', 'invoicing');
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

it('shows amber not-ready with the plain issue list when z-heart says the client cannot sign yet', function () {
    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response([
            'status' => 'ok',
            'ready' => false,
            'issues' => [
                ['field' => 'client_profile.legal_name_ar', 'message' => 'The legal name in Arabic is missing.'],
                ['field' => 'client_profile.address_building', 'message' => 'The building number is not valid. It must be exactly 4 digits.'],
                ['field' => 'zatca.onboarding', 'message' => 'This business is not onboarded with ZATCA for the Simulation environment yet.'],
            ],
            'client' => ['name' => 'Riyadh Pipes', 'environment' => 'simulation', 'active' => true, 'onboarded' => false],
        ], 200),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789')
        ->call('testZheartConnection')
        ->assertSet('zheartTestStatus', 'warn')
        ->assertSet('zheartTestReason', 'not_ready')
        ->assertSeeHtml('data-testid="business-zheart-test-issues"')
        ->assertSee(__('business.zheart.test_not_ready'), false)
        ->assertSee(__('business.zheart.test_not_ready_hint'), false)
        ->assertSee(__('business.zheart.issues.client_profile.legal_name_ar'), false)
        ->assertSee(__('business.zheart.issues.client_profile.address_building'), false)
        ->assertSee(__('business.zheart.issues.zatca.onboarding', ['environment' => 'Simulation']), false)
        ->assertDontSee(__('business.zheart.test_connected_heading'), false)
        ->assertSeeHtml('data-testid="business-zheart-test-card"');
});

it('stays green when z-heart reports ready, and when an older z-heart sends no readiness block', function (array $extra) {
    Http::fake([
        'https://z-heart.example.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
        'https://z-heart.example.test/api/v1/auth' => Http::response(array_merge([
            'status' => 'ok',
            'client' => ['name' => 'Riyadh Pipes', 'environment' => 'simulation', 'active' => true, 'onboarded' => true],
        ], $extra), 200),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('zheartBaseUrl', 'https://z-heart.example.test')
        ->set('zheartApiKeyInput', 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789')
        ->call('testZheartConnection')
        ->assertSet('zheartTestStatus', 'ok')
        ->assertSet('zheartTestIssues', [])
        ->assertSee(__('business.zheart.test_connected_heading'), false)
        ->assertDontSeeHtml('data-testid="business-zheart-test-issues"');
})->with([
    'ready' => [['ready' => true, 'issues' => []]],
    'older z-heart' => [[]],
]);
