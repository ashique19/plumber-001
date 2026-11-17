<?php

use App\Livewire\Admin\StaffProfile;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders staff profile for authenticated user', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->assertSee(__('profile.title'), false)
        ->assertSee(__('profile.email'), false);
});