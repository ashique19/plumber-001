<?php

use App\Livewire\Operations\TeamHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Operations\BranchAdminService;
use App\Business\BusinessContext;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::query()->firstOrFail();
        $this->owner = StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@fancy-coffee.test')->firstOrFail();
});

it('stores branch map coordinates', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->set('tab', 'branches')
        ->call('startBranch')
        ->set('branchCode', 'geo-hub')
        ->set('branchName', 'Geo Hub')
        ->set('branchLat', '24.7743')
        ->set('branchLng', '46.7386')
        ->call('saveBranch')
        ->assertHasNoErrors();

    $branch = Branch::query()
        ->where('code', 'geo-hub')
        ->firstOrFail();

    expect($branch->contact())
        ->toMatchArray([
            'lat' => '24.7743',
            'lng' => '46.7386',
            'maps_url' => 'https://www.google.com/maps?q=24.7743,46.7386',
        ]);
});

it('allows owner to add a branch with contact fields', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->set('tab', 'branches')
        ->call('startBranch')
        ->set('branchCode', 'test-hub')
        ->set('branchName', 'Test Hub')
        ->set('branchNameAr', 'فرع تجريبي')
        ->set('branchAddress', 'King Fahd Rd')
        ->set('branchCity', 'Riyadh')
        ->set('branchPhone', '+966500000000')
        ->set('branchEmail', 'hub@fancy-coffee.test')
        ->set('branchActive', true)
        ->call('saveBranch')
        ->assertHasNoErrors();

    $branch = Branch::query()
        
        ->where('code', 'test-hub')
        ->firstOrFail();

    expect($branch->name)->toBe('Test Hub')
        ->and($branch->contact())
        ->toMatchArray([
            'phone' => '+966500000000',
            'email' => 'hub@fancy-coffee.test',
            'city' => 'Riyadh',
        ]);
});

it('deactivates a branch and hides it from POS home', function () {
    $branch = Branch::query()
        
        ->where('code', 'jad-main')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->call('deactivateBranch', $branch->id);

    $branch->refresh();
    expect($branch->is_active)->toBeFalse();

    BusinessContext::set($this->tenant);

    Livewire::actingAs($this->cashier, 'staff')
        ->test(\App\Livewire\Pos\Home::class)
        ->assertSee(__('pos.empty_branches'), false);

    expect($branch->fresh()->is_active)->toBeFalse();
});

it('reactivates a branch', function () {
    $branch = Branch::query()
        
        ->where('code', 'jad-main')
        ->firstOrFail();
    $branch->update(['is_active' => false]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->call('reactivateBranch', $branch->id);

    expect($branch->fresh()->is_active)->toBeTrue();
});

it('refuses to delete a branch with POS orders and allows deleting an empty branch', function () {
    $seeded = Branch::query()
        
        ->where('code', 'jad-main')
        ->firstOrFail();

    expect(app(BranchAdminService::class)->canDelete($seeded))->toBeFalse();

    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->call('deleteBranch', $seeded->id);

    expect($seeded->fresh())->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->call('startBranch')
        ->set('branchCode', 'disposable')
        ->set('branchName', 'Disposable')
        ->call('saveBranch');

    $disposable = Branch::query()
        ->where('code', 'disposable')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->call('deleteBranch', $disposable->id);

    expect(Branch::query()->find($disposable->id))->toBeNull();
});
