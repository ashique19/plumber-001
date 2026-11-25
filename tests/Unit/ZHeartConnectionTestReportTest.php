<?php

use App\Models\BusinessSetting;
use App\Support\Invoicing\ZHeartConnectionTestReport;

it('builds a success card with dashes for missing fields', function () {
    $report = ZHeartConnectionTestReport::successFromAuthPayload([
        'status' => 'ok',
        'client' => [
            'name' => 'Acme',
            'environment' => 'production',
            'active' => true,
            'onboarded' => true,
        ],
        'api_key' => [
            'label' => 'Primary',
            'last4' => '1234',
        ],
        'ip_allowlist_enabled' => true,
    ]);

    expect($report['card']['client_name'])->toBe('Acme')
        ->and($report['card']['vat_number'])->toBe('—')
        ->and($report['card']['environment_production'])->toBeTrue()
        ->and($report['card']['key_last4'])->toBe('…1234')
        ->and($report['card']['ip_allowlist'])->toBe(__('business.zheart.allowlist_on'));
});

it('warns on vat mismatch against business settings', function () {
    $business = new BusinessSetting;
    $business->settings = ['legal' => ['vat_number' => '300012345600003']];

    $report = ZHeartConnectionTestReport::successFromAuthPayload([
        'status' => 'ok',
        'client' => [
            'name' => 'Acme',
            'vat_number' => '300099999900003',
            'environment' => 'sandbox',
        ],
    ], $business);

    expect($report['warnings'])->not->toBeEmpty()
        ->and($report['warnings'][0])->toContain('300012345600003');
});

it('flags csid expiring within thirty days', function () {
    $report = ZHeartConnectionTestReport::successFromAuthPayload([
        'status' => 'ok',
        'client' => [
            'name' => 'Acme',
            'csid_expires_at' => now()->addDays(10)->toIso8601String(),
        ],
    ]);

    expect($report['card']['csid_expires_soon'])->toBeTrue()
        ->and($report['warnings'])->toContain(__('business.zheart.test_warn_csid_expiring'));
});
it('treats a missing readiness block from older z-heart as unknown, not as a problem', function () {
    $report = ZHeartConnectionTestReport::successFromAuthPayload([
        'status' => 'ok',
        'client' => ['name' => 'Acme', 'environment' => 'simulation'],
    ]);

    expect($report['ready'])->toBeNull()
        ->and($report['issues'])->toBe([])
        ->and($report['message'])->not->toBe(__('business.zheart.test_not_ready'));
});

it('turns z-heart readiness issues into plain translated sentences', function () {
    app()->setLocale('ar');

    $report = ZHeartConnectionTestReport::successFromAuthPayload([
        'status' => 'ok',
        'ready' => false,
        'issues' => [
            ['field' => 'client_profile.address_postal', 'message' => 'The postal code is missing.'],
            ['field' => 'zatca.onboarding', 'message' => 'Not onboarded.'],
            ['field' => 'something.new', 'message' => 'A brand new problem from Z-Heart.'],
            ['field' => 'client_profile.address_postal', 'message' => 'The postal code is missing.'],
        ],
        'client' => ['name' => 'Acme', 'environment' => 'simulation'],
    ]);

    expect($report['ready'])->toBeFalse()
        ->and($report['message'])->toBe('متصل، لكنه غير جاهز لتوقيع الفواتير')
        ->and($report['issues'])->toBe([
            'الرمز البريدي غير موجود أو غير صحيح. يجب أن يتكون من 5 أرقام بالضبط.',
            'هذا النشاط غير مسجّل لدى هيئة الزكاة والضريبة والجمارك (ZATCA) في بيئة Simulation بعد.',
            'A brand new problem from Z-Heart.',
        ]);
});

it('has every readiness string in all plumber languages', function () {
    $english = (fn () => require base_path('lang/en/business.php'))();
    $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            $out = is_array($v) ? array_merge($out, $flatten($v, $prefix.$k.'.')) : array_merge($out, [$prefix.$k => $v]);
        }

        return $out;
    };
    $keys = array_keys($flatten(['issues' => $english['zheart']['issues'], 'test_not_ready' => 1, 'test_not_ready_hint' => 1]));

    foreach (['ar', 'bn'] as $locale) {
        $strings = $flatten((fn () => require base_path("lang/{$locale}/business.php"))()['zheart']);
        foreach ($keys as $key) {
            expect($strings[$key] ?? null)->not->toBeNull()
                ->and($strings[$key])->not->toBe($flatten($english['zheart'])[$key], "{$locale}: {$key} is untranslated");
        }
    }
});
