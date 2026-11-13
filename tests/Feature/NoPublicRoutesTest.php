<?php

use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
});

it('does not register public storefront or café routes', function () {
    $names = collect(Route::getRoutes())->map->getName()->filter()->values();

    expect($names)->not->toContain('storefront.home', 'storefront.menu', 'cafe.menu');

    $getUris = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($getUris)->toContain('/', 'forgot-password', 'healthz')
        ->and(Route::has('login'))->toBeTrue();
});

it('returns not found for legacy public paths', function () {
    $this->get('/menu')->assertNotFound();
    $this->get('/storefront')->assertNotFound();
    $this->get('/cafe')->assertNotFound();
});
