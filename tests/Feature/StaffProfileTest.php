<?php

use App\Livewire\Admin\StaffProfile;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('exposes validateLiveField for shared form blur hooks', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->call('validateLiveField', 'email')
        ->assertHasNoErrors();
});

it('renders staff profile for authenticated user', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->assertSee(__('profile.title'), false)
        ->assertSee(__('profile.save'), false)
        ->assertDontSee(__('business.save'), false);
});

it('persists profile fields without blurring inputs and shows a success toast', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->set('name', 'Profile Owner Updated')
        ->set('email', 'owner-updated@example.test')
        ->set('locale', 'en')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: __('profile.saved', [], 'en'));

    $fresh = $this->owner->fresh();
    expect($fresh->name)->toBe('Profile Owner Updated')
        ->and($fresh->email)->toBe('owner-updated@example.test')
        ->and(session('locale'))->toBe('en');
});

it('shows validation errors in the active locale', function () {
    app()->setLocale('ar');

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

it('requires matching confirmation when changing password', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->set('password', 'New-Secure-Pass-12')
        ->set('password_confirmation', 'Mismatch-Pass-12')
        ->call('save')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('updates the password only when a new password is provided', function () {
    $newPassword = 'Brand-New-Secure-12';

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Hash::check($newPassword, $this->owner->fresh()->password))->toBeTrue();

    session(['locale' => 'en']);

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check($newPassword, $this->owner->fresh()->password))->toBeTrue();
});

it('stores the chosen language in session when saved', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->set('locale', 'bn')
        ->call('save')
        ->assertHasNoErrors();

    expect(session('locale'))->toBe('bn');
});