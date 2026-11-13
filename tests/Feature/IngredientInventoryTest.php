<?php

use App\Livewire\Catalog\InventoryManager;
use App\Models\Ingredient;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

function inventorySetup(): array
{
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();

    $owner = $cashier;
    $owner->syncRoles(['owner']);

    $ingredient = Ingredient::query()->create([
        'branch_id' => null,
        'name_en' => 'Milk',
        'name_ar' => 'حليب',
        'unit' => 'ml',
        'quantity_on_hand' => 500,
        'reorder_level' => 200,
        'low_stock_threshold' => 200,
        'is_active' => true,
    ]);

    return compact('tenant', 'branch', 'cashier', 'product', 'ingredient');
}

it('does not 500 when saveAdjust called without adjust id', function () {
    ['cashier' => $cashier] = inventorySetup();

    Livewire::actingAs($cashier, 'staff')
        ->test(InventoryManager::class)
        ->set('adjustAmount', '10')
        ->call('saveAdjust')
        ->assertStatus(200);
});
