<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Purchases\CreatePurchase;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StaffUser;
use App\Models\Supplier;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->customer = Customer::query()->orderBy('id')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
    $this->branch = Branch::query()->firstOrFail();
});

it('clears customer selection and buyer snapshot on standard invoice create', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $this->customer->id)
        ->assertSet('selectedCustomerId', $this->customer->id)
        ->set('buyerContactOverride', true)
        ->set('buyerPhone', '0599999999')
        ->call('clearSelectedCustomer')
        ->assertSet('selectedCustomerId', null)
        ->assertSet('buyerContactOverride', false)
        ->assertSet('buyerName', '')
        ->assertSet('buyerVat', '')
        ->assertSet('customerSearch', '')
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->assertHasErrors(['selectedCustomerId']);
});

it('exposes clear control markup when customer is linked', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->call('selectDocumentCustomer', $this->customer->id)
        ->html();

    $clearLabel = __('invoices.clear_customer');

    expect($html)
        ->toContain('data-testid="document-customer-clear"')
        ->toContain($clearLabel)
        ->toContain('wire:click="clearSelectedCustomer"')
        ->toContain('wire:click="clearCustomerPicker"')
        ->toContain('title="'.e($clearLabel).'"');
});

it('clears purchase supplier selection', function () {
    $supplier = Supplier::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Vendor Clear Test',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->call('selectPurchaseSupplier', $supplier->id)
        ->assertSet('supplierId', $supplier->id)
        ->call('clearSelectedSupplier')
        ->assertSet('supplierId', null)
        ->assertSet('supplierSearch', '');
});