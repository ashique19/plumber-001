<?php

use App\Livewire\Pos\Home;
use App\Livewire\Reports\OwnerAnalytics;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\StaffBranchContext;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

function leaveOnlyBranchActive(string $code): Branch
{
    Branch::query()->update(['is_active' => false]);
    $branch = Branch::query()->where('code', $code)->firstOrFail();
    $branch->forceFill(['is_active' => true])->save();

    return $branch->fresh();
}

it('resolves the sole accessible branch without showing a selector', function () {
    $branch = leaveOnlyBranchActive('ruh-main');

    expect(StaffBranchContext::selectorVisible($this->owner))->toBeFalse()
        ->and(StaffBranchContext::resolve($this->owner)?->id)->toBe($branch->id);
});

it('redirects pos home to the terminal when only one branch is active', function () {
    $branch = leaveOnlyBranchActive('ruh-main');

    Livewire::test(Home::class)
        ->assertRedirect(route('admin.pos.terminal', $branch, absolute: false));
});

it('sends cashiers with one branch straight to the terminal after login', function () {
    leaveOnlyBranchActive('ruh-main');
    auth('staff')->logout();

    $response = $this->post('/login', [
        'email' => $this->cashier->email,
        'password' => 'TestPassword!2026',
    ]);

    $branch = Branch::query()->where('code', 'ruh-main')->firstOrFail();
    $response->assertRedirect(route('admin.pos.terminal', $branch, absolute: false));
});

it('auto-selects the branch on reports when only one branch is accessible', function () {
    $branch = leaveOnlyBranchActive('ruh-main');

    Livewire::test(OwnerAnalytics::class)
        ->assertSet('branchId', $branch->id)
        ->assertDontSee(__('reports.all_branches'), false)
        ->assertSee($branch->name, false);
});

it('shows branch picker on reports when multiple branches are active', function () {
    Branch::query()->update(['is_active' => true]);
    $this->owner->forceFill(['branch_id' => null])->save();

    expect(StaffBranchContext::selectorVisible($this->owner->fresh()))->toBeTrue();

    Livewire::test(OwnerAnalytics::class)
        ->assertSee(__('reports.all_branches'), false);
});

it('prefers last-used branch from session when multiple branches exist', function () {
    Branch::query()->update(['is_active' => true]);
    $this->owner->forceFill(['branch_id' => null])->save();
    $ruh = Branch::query()->where('code', 'ruh-main')->firstOrFail();
    $jad = Branch::query()->where('code', 'jad-main')->firstOrFail();

    $this->startSession();
    $this->withSession([StaffBranchContext::SESSION_KEY => $jad->id]);

    expect(StaffBranchContext::resolve($this->owner)?->id)->toBe($jad->id);

    StaffBranchContext::remember($ruh);
    expect(StaffBranchContext::resolve($this->owner)?->id)->toBe($ruh->id);
});

it('skips branch selection page via http when a single branch is active', function () {
    $branch = leaveOnlyBranchActive('ruh-main');

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.pos.home'))
        ->assertRedirect(route('admin.pos.terminal', $branch, absolute: false));
});