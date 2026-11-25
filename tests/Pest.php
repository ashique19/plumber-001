<?php

use App\Business\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesZHeartSigning;
use Tests\TestCase;

require_once __DIR__.'/Support/PosTestHelpers.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->use(FakesZHeartSigning::class)
    ->beforeEach(function () {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->fakeZHeartSigning();
        config([
            'app.locale' => 'bn',
            'locales.default' => 'bn',
            'app.enabled_locales' => ['en', 'bn', 'ar'],
        ]);
    })
    ->afterEach(function () {
        BusinessContext::clear();
        \Tests\Support\ZHeartFakeSeller::$block = null;
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(FakesZHeartSigning::class)
    ->group('install-sql')
    ->beforeEach(function () {
        $this->fakeZHeartSigning();
    })
    ->afterEach(function () {
        BusinessContext::clear();
    })
    ->in('InstallSql');
