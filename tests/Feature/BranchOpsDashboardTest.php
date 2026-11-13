<?php

use App\Livewire\Ops\BranchDashboard;
use App\Models\Branch;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@fancy-coffee.test')->firstOrFail();
});

it('renders cafe branch ops dashboard with chart scale for hourly totals', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BranchDashboard::class, ['branch' => $this->branch])
        ->assertOk()
        ->assertViewHas('maxHour', fn ($max) => is_int($max) || is_float($max))
        ->assertViewHas('maxHour', fn ($max) => $max >= 1);

});
