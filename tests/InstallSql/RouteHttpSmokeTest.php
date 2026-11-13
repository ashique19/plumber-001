<?php

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\HttpRouteSmokeCatalog;
use Tests\Support\InstallSqlMysqlTestEnvironment;
use Tests\Support\InstallSqlRouteFixtures;

beforeEach(function () {
    $this->withoutVite();

    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; install.sql route smoke requires MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->branch = Branch::query()->where('code', 'ruh-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $order = InstallSqlRouteFixtures::ensurePaidPosOrder();
    $this->routes = HttpRouteSmokeCatalog::getRoutes(
        $this->branch,
        InstallSqlRouteFixtures::invoice(),
        $order,
    );
});

afterEach(function () {
    Config::set('database.default', 'sqlite');
    DB::purge('mysql');
    DB::purge('sqlite');
    BusinessContext::clear();
});

/**
 * @param  \Illuminate\Testing\TestResponse|\Symfony\Component\HttpFoundation\Response  $response
 */
function installSqlSmokeResponseStatus($response): int
{
    return $response->getStatusCode();
}

it('serves every GET route as a guest without a server error', function () {
    foreach ($this->routes as [$url, $name]) {
        $response = $this->get($url);

        expect(installSqlSmokeResponseStatus($response))
            ->not->toBe(500, "Guest GET {$url}".($name ? " ({$name})" : '').' must not return 500');
    }
});

it('serves every GET route as the owner without a server error', function () {
    $this->actingAs($this->owner, 'staff');

    foreach ($this->routes as [$url, $name]) {
        $response = $this->get($url);

        expect(installSqlSmokeResponseStatus($response))
            ->not->toBe(500, "Owner GET {$url}".($name ? " ({$name})" : '').' must not return 500');
    }
});

it('exposes forgot-password to guests', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertStatus(200);
});
