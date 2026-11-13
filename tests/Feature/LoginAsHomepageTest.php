<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('serves the staff login form at the site root', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('pos.login_title'), false)
        ->assertSee('auth-shell__bg', false)
        ->assertSee('auth-glass-card', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('locale=ur', false)
        ->assertDontSee('locale=hi', false);
});

it('authenticates staff via POST login and redirects into admin', function () {
  $response = $this->post('/login', [
      'email' => $this->owner->email,
      'password' => 'TestPassword!2026',
  ]);

    $response->assertRedirect(route('admin.pos.home', absolute: false));
    $this->assertAuthenticatedAs($this->owner, 'staff');
});
