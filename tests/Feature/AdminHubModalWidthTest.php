<?php

use App\Livewire\Admin\StaffUsersHub;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Expenses\ExpensesHub;
use App\Livewire\Purchases\SupplierHub;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('marks xl hub modals with the shared wide form sheet class', function (string $component, string $openAction, array $params = []) {
    $test = Livewire::actingAs($this->owner, 'staff')->test($component, $params);
    if ($openAction !== '') {
        $test->call($openAction);
    }

    $test->assertSeeHtml('x-modal--form-sheet');
})->with([
    'customers' => [CustomerHub::class, 'startCustomer'],
    'suppliers' => [SupplierHub::class, 'startCreate'],
    'staff users' => [StaffUsersHub::class, 'startCreate'],
    'expenses' => [ExpensesHub::class, 'openForm'],
]);