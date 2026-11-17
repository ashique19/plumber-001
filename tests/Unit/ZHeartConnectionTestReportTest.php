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