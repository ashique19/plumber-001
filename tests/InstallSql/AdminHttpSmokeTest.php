<?php

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthenticatedRouteCatalog;
use Tests\Support\InstallSqlMysqlTestEnvironment;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->withoutVite();

    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; install.sql admin smoke is skipped.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

afterEach(function () {
    Config::set('database.default', 'sqlite');
    DB::purge('mysql');
    DB::purge('sqlite');
});

it('serves the guest login homepage from an install.sql database', function () {
    $this->get('/')
        ->assertOk()
        ->assertStatus(200);
});

it('serves every main admin page from an install.sql database without a server error', function () {
    $this->actingAs($this->owner, 'staff');

    foreach (AuthenticatedRouteCatalog::adminRoutes($this->branch) as [$routeName, $parameters, $expectedStatus]) {
        $url = route($routeName, $parameters);

        $response = $this->get($url);

        expect($response->status())
            ->not->toBe(500, "GET {$url} ({$routeName}) must not return 500 on install.sql schema")
            ->and($response->status())
            ->toBe($expectedStatus, "GET {$url} ({$routeName})");
    }
});
