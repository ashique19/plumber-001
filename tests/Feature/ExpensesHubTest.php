<?php

use App\Livewire\Expenses\ExpensesHub;
use App\Livewire\Reports\OwnerAnalytics;
use App\Models\Branch;
use App\Models\BusinessExpense;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('loads the expenses hub for the owner', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->assertSee(__('expenses.title'), false)
        ->assertStatus(200);
});

it('records an expense and includes it in profit report totals', function () {
    $spentOn = now()->toDateString();

    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->set('showForm', true)
        ->set('spentOn', $spentOn)
        ->set('formCategory', 'rent')
        ->set('vendor', 'Landlord LLC')
        ->set('amountExVat', '500')
        ->call('saveExpense')
        ->assertHasNoErrors();

    expect(BusinessExpense::query()->count())->toBe(1);

    Livewire::actingAs($this->owner, 'staff')
        ->test(OwnerAnalytics::class, [
            'dateFrom' => $spentOn,
            'dateTo' => $spentOn,
        ])
        ->assertSee(__('reports.operating_expenses_ex_vat'), false)
        ->assertSee('500.00', false);
});