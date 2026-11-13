<?php

use App\Livewire\Pos\Terminal;
use App\Models\Ingredient;
use App\Models\PosOrder;
use App\Models\ProductIngredient;
use App\Business\BusinessContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    \App\Support\AuthRoleProvisioner::seedStaffRoles();
});

afterEach(function () {
    BusinessContext::clear();
    
    \Illuminate\Support\Facades\Auth::guard('staff')->logout();
});

function stockCapSetup(int $onHandMl = 9600, float $perUnit = 300.0): array
{
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();

    Ingredient::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'name' => 'Beans',
        'name_en' => 'Beans',
        'name_ar' => 'حبوب',
        'unit' => 'g',
        'quantity_on_hand' => $onHandMl,
        'reorder_level' => 100,
        'low_stock_threshold' => 100,
        'is_active' => true,
    ]);

    $ingredient = Ingredient::query()
        
        ->where('name_en', 'Beans')
        ->firstOrFail();

    ProductIngredient::query()->create([
        'product_id' => $product->id,
        'ingredient_id' => $ingredient->id,
        'quantity_per_unit' => $perUnit,
    ]);

    return compact('tenant', 'branch', 'cashier', 'product');
}

it('dispatches stock cap toast when typing above available inventory', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = stockCapSetup();
    BusinessContext::set($tenant);

    Livewire::actingAs($cashier, 'staff')
        ->test(Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('applyLineQuantity', 0, 50)
        ->assertStatus(200)
        ->assertDispatched('toast', type: 'warning', dedupe: false);

    $order = PosOrder::query()->latest('id')->first();
    expect((int) floor((float) ($order->line_items[0]['quantity'] ?? 0)))->toBe(32);
});

it('dispatches max qty toast when typing above 999 without server 500', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = stockCapSetup(999_999);
    BusinessContext::set($tenant);

    Livewire::actingAs($cashier, 'staff')
        ->test(Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('applyLineQuantity', 0, 1000)
        ->assertStatus(200)
        ->assertDispatched('toast', type: 'warning', dedupe: false)
        ->assertHasNoErrors();
});

it('handles rapid increment at stock ceiling without errors', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = stockCapSetup(6000);
    BusinessContext::set($tenant);

    $component = Livewire::actingAs($cashier, 'staff')
        ->test(Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id);

    foreach (range(1, 25) as $_) {
        $component->call('incrementLine', 0)->assertStatus(200);
    }

    expect($component->errors())->toBeEmpty();
});
