<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers staff login and admin routes', function () {
    expect(route('login', absolute: false))->toBe('/')
        ->and(route('admin.dashboard', absolute: false))->toBe('/admin/dashboard');
});
