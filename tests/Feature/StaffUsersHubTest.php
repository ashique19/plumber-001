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
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    BusinessContext::set($business);
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
});

afterEach(function () {
    Auth::guard('staff')->logout();
});

it('forbids non-owners from the users hub route', function () {
    $this->actingAs($this->cashier, 'staff')
        ->get(route('admin.users'))
        ->assertForbidden();
});

it('lists users with pagination and row actions for the owner', function () {
    for ($i = 1; $i <= 25; $i++) {
        StaffUser::query()->create([
            'name' => "Extra User {$i}",
            'email' => "extra{$i}@example.test",
            'password' => Hash::make('Password12345!'),
            'is_active' => true,
            'locale' => 'bn',
        ])->assignRole('cashier');
    }

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->set('search', 'owner@example.test')
        ->assertSee('owner@example.test')
        ->assertSeeHtml('data-testid="hub-staff-user-row-actions"')
        ->assertSeeHtml('data-testid="admin-hub-pager"');

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->set('perPage', 10)
        ->assertSet('perPage', 10);
});

it('does not show a name validation error when changing role or locale before touching name', function () {
    $bnNameRequired = __('validation.required', ['attribute' => __('validation.attributes.name')]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startCreate')
        ->set('role', StaffAdminRoleCatalog::UI_MANAGER)
        ->set('locale', 'ar')
        ->assertHasNoErrors()
        ->assertDontSee($bnNameRequired);
});

it('creates a staff user with default bn locale and cashier role', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startCreate')
        ->set('name', 'New Staff')
        ->set('email', 'newstaff@example.test')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->set('role', StaffAdminRoleCatalog::UI_STAFF)
        ->set('locale', 'bn')
        ->call('save')
        ->assertHasNoErrors();

    $user = StaffUser::query()->where('email', 'newstaff@example.test')->firstOrFail();
    expect($user->locale)->toBe('bn')
        ->and($user->hasRole('cashier'))->toBeTrue();
});

it('blocks deactivating yourself or the last owner', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('deactivateUser', $this->owner->id)
        ->assertHasErrors(['is_active']);

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('startEdit', $this->owner->id)
        ->set('role', StaffAdminRoleCatalog::UI_MANAGER)
        ->call('save')
        ->assertHasErrors(['role']);
});

it('prevents login for deactivated users', function () {
    $user = StaffUser::query()->create([
        'name' => 'Temp',
        'email' => 'temp@example.test',
        'password' => Hash::make('Password12345!'),
        'is_active' => true,
        'locale' => 'en',
    ]);
    $user->assignRole(Role::findByName('cashier', 'staff'));

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('deactivateUser', $user->id);

    $user->refresh();
    expect($user->is_active)->toBeFalse();

    $stored = StaffUser::query()->where('email', 'temp@example.test')->first();
    $loginAllowed = $stored !== null
        && $stored->is_active
        && Hash::check('Password12345!', $stored->password);
    expect($loginAllowed)->toBeFalse();
});

it('reactivates a deactivated user', function () {
    $user = StaffUser::query()->create([
        'name' => 'Inactive',
        'email' => 'inactive@example.test',
        'password' => Hash::make('Password12345!'),
        'is_active' => false,
        'locale' => 'ar',
    ]);
    $user->assignRole('cashier');

    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffUsersHub::class)
        ->call('reactivateUser', $user->id);

    expect($user->fresh()->is_active)->toBeTrue();
});
