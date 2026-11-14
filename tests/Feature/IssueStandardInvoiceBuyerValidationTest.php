<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('requires vat or cr for standard b2b buyer on issue', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('buyerName', 'Buyer LLC')
        ->set('addrBuilding', '10')
        ->set('addrStreet', 'King Fahd Rd')
        ->set('addrCity', 'Riyadh')
        ->set('addrPostal', '11564')
        ->set('lines', [
            ['name' => 'Service', 'name_ar' => '', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '15'],
        ])
        ->call('issue')
        ->assertHasErrors(['buyerVat', 'buyerCr']);
});

it('passes buyer validation when cr is provided without vat', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('buyerName', 'Buyer LLC')
        ->set('buyerCr', '1010998899')
        ->set('addrBuilding', '10')
        ->set('addrStreet', 'King Fahd Rd')
        ->set('addrCity', 'Riyadh')
        ->set('addrPostal', '11564')
        ->set('lines', [
            ['name' => 'Service', 'name_ar' => '', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '15'],
        ])
        ->call('issue')
        ->assertHasNoErrors(['buyerVat', 'buyerCr']);
});