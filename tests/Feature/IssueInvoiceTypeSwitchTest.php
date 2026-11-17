<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('switches invoice type from simplified to standard and back without error', function (bool $withLine) {
    $test = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified']);

    if ($withLine) {
        $test->call('addLineFromProduct', $this->product->id);
    }

    expect($test->get('mode'))->toBe('simplified');
    if ($withLine) {
        expect($test->get('lines'))->not->toBeEmpty();
    } else {
        expect($test->get('lines'))->toBeEmpty();
    }

    $test->set('mode', 'standard')
        ->assertSet('mode', 'standard')
        ->assertSuccessful();

    if ($withLine) {
        expect($test->get('lines'))->not->toBeEmpty();
    }

    $test->set('mode', 'simplified')
        ->assertSet('mode', 'simplified')
        ->assertSuccessful();
})->with([
    'empty cart' => [false],
    'with line item' => [true],
]);

it('mounts the standard issue route', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->assertSet('mode', 'standard')
        ->assertSuccessful();
});