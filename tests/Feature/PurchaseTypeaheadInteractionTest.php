<?php

use App\Business\BusinessContext;
use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Purchases\CreatePurchase;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StaffUser;
use App\Models\Supplier;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->firstOrFail();
});

it('does not validate purchase form when supplier search loses focus', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('supplierSearch', 'Acme')
        ->call('validateLiveField', 'supplierSearch')
        ->assertHasNoErrors()
        ->assertSet('supplierId', null);

    expect(Supplier::query()->count())->toBe(0);
});

it('does not submit purchase form when pressing enter on supplier search', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('supplierSearch', 'typing');

    expect($component->instance()->supplierId)->toBeNull();
});

it('does not validate invoice customer search on blur', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('customerSearch', '050')
        ->call('validateLiveField', 'customerSearch')
        ->assertHasNoErrors();
});

it('adds a purchase line from product typeahead search', function () {
    $supplier = Supplier::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Vendor',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 1,
        'cost_ex_vat' => 12,
        'price_ex_vat' => 40,
        'vat_rate' => 15,
        'name_en' => 'Elbow',
        'name_ar' => 'كوع',
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('supplierId', $supplier->id)
        ->call('addPurchaseLineFromProduct', $product->id)
        ->assertSet('lines.0.product_id', $product->id)
        ->assertHasNoErrors();
});

it('renders translated purchase notes label in bn', function () {
    $this->actingAs($this->owner, 'staff');

    $html = (string) $this->get(route('admin.purchases.create', ['locale' => 'bn']))->getContent();

    expect($html)->toContain(__('purchases.notes', [], 'bn'))
        ->and($html)->not->toContain('>Notes<');
});