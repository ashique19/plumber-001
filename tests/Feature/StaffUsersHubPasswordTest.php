<?php

use App\Business\BusinessContext;
use App\Livewire\Admin\StaffUsersHub;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Staff\StaffAdminRoleCatalog;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

afterEach(function () {
    Auth::guard('staff')->logout();
});

it('creates a user with password fields visible and allows login', function () {
    $password = 'SignIn-Pass-88';

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startCreate')
        ->assertSeeHtml('data-testid="staff-user-password"')
        ->assertSeeHtml('data-testid="staff-user-password-confirmation"')
        ->assertSeeHtml('data-testid="staff-user-generate-password"')
        ->set('name', 'Loginable Staff')
        ->set('email', 'loginable@example.test')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->set('role', StaffAdminRoleCatalog::UI_STAFF)
        ->set('locale', 'en')
        ->call('save')
        ->assertHasNoErrors();

    $user = StaffUser::query()->where('email', 'loginable@example.test')->firstOrFail();
    expect(Hash::check($password, $user->password))->toBeTrue();

    Auth::guard('staff')->logout();

    expect(Auth::guard('staff')->attempt([
        'email' => 'loginable@example.test',
        'password' => $password,
    ], false))->toBeTrue();
});

it('keeps the existing password when edit leaves reset fields blank', function () {
    $originalHash = $this->owner->password;

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startEdit', $this->owner->id)
        ->set('name', 'Owner Renamed')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->owner->fresh()->name)->toBe('Owner Renamed')
        ->and($this->owner->fresh()->password)->toBe($originalHash);
});

it('resets password on edit when a new password is provided', function () {
    $target = StaffUser::query()->create([
        'name' => 'Reset Target',
        'email' => 'reset-target@example.test',
        'password' => Hash::make('Old-Password-99'),
        'is_active' => true,
        'locale' => 'en',
    ]);
    $target->assignRole('cashier');

    $newPassword = 'Fresh-Reset-77';

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startEdit', $target->id)
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check($newPassword, $target->fresh()->password))->toBeTrue()
        ->and(Hash::check('Old-Password-99', $target->fresh()->password))->toBeFalse();
});

it('fills password fields when generate is used', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startCreate')
        ->call('generatePassword')
        ->assertSeeHtml('data-testid="staff-user-generated-password-banner"');

    $plain = (string) $component->get('generatedPasswordPlain');
    expect(strlen($plain))->toBeGreaterThanOrEqual(12)
        ->and($component->get('password'))->toBe($plain)
        ->and($component->get('password_confirmation'))->toBe($plain);
});
