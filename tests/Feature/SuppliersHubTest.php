<?php

use App\Business\BusinessContext;
use App\Livewire\Purchases\CreatePurchase;
use App\Livewire\Purchases\SupplierHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Purchase;
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

it('registers admin suppliers route', function () {
    expect(route('admin.suppliers', absolute: false))->toBe('/admin/suppliers');
});

it('creates updates and deletes a supplier without purchases', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->call('startCreate')
        ->set('formName', 'Gulf Parts Co')
        ->set('formPhone', '0501234567')
        ->set('formVat', '300000000000003')
        ->call('saveSupplier')
        ->assertHasNoErrors();

    $supplier = Supplier::query()->where('name', 'Gulf Parts Co')->first();
    expect($supplier)->not->toBeNull()
        ->and($supplier->branch_id)->toBe($this->branch->id);

    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->call('selectSupplier', $supplier->id)
        ->set('formContactPerson', 'Sara')
        ->call('saveSupplier')
        ->assertHasNoErrors();

    $supplier->refresh();
    expect($supplier->contact_person)->toBe('Sara');

    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->call('selectSupplier', $supplier->id)
        ->call('deleteSupplier')
        ->assertHasNoErrors();

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeFalse();
});

it('blocks supplier delete when purchases exist', function () {
    $supplier = Supplier::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Locked Vendor',
        'is_active' => true,
    ]);

    Purchase::query()->create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'PUR-TEST-1',
        'purchased_at' => now(),
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->call('selectSupplier', $supplier->id)
        ->call('deleteSupplier')
        ->assertHasNoErrors();

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('renders supplier hub modal with compact three-column field grid on large screens', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->call('startCreate')
        ->assertSee('x-modal-form__fields--3', false)
        ->assertSee('x-modal--identity', false);
});

it('renders searchable empty state without side panel', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(SupplierHub::class)
        ->assertSee('data-admin-search-input', false)
        ->assertSee(__('suppliers.empty_list'), false)
        ->assertDontSee(__('suppliers.pick_one'), false);
});

it('enables suppliers nav link on dashboard', function () {
    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.dashboard', ['locale' => 'en']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('admin_nav.items.suppliers', [], 'en'))
        ->and($html)->toContain(route('admin.suppliers'));
});

it('quick-adds supplier on purchase form and records purchase', function () {
    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 2,
        'cost_ex_vat' => 10,
        'low_stock_threshold' => 5,
        'price_ex_vat' => 50,
        'vat_rate' => 15,
        'name_en' => 'Copper elbow',
        'name_ar' => 'كوع نحاس',
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('quickSupplierName', 'Al Rajhi Supplies')
        ->set('quickSupplierVat', '300000000000003')
        ->set('quickSupplierPhone', '0500000001')
        ->call('saveQuickSupplier')
        ->assertHasNoErrors()
        ->set('lines', [
            ['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '20', 'vat_rate' => '15'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Supplier::query()->where('name', 'Al Rajhi Supplies')->exists())->toBeTrue();
});