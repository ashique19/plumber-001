<?php

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Tests\Support\AuthenticatedRouteCatalog;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('serves the guest login homepage without a server error', function () {
    $this->get('/')
        ->assertOk()
        ->assertStatus(200);
});

it('serves every main admin page for the owner without a server error', function () {
    $this->actingAs($this->owner, 'staff');

    foreach (AuthenticatedRouteCatalog::adminRoutes($this->branch) as [$routeName, $parameters, $expectedStatus]) {
        $url = route($routeName, $parameters);

        $response = $this->get($url);

        expect($response->status())
            ->not->toBe(500, "GET {$url} ({$routeName}) must not return 500")
            ->and($response->status())
            ->toBe($expectedStatus, "GET {$url} ({$routeName})");
    }
});
