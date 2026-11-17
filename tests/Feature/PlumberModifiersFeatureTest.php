<?php

use App\Business\BusinessContext;
use App\Livewire\Catalog\MenuManager;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductModifier;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->firstOrFail();
});

it('hides catalog modifier UI when the feature flag is off', function () {
    config(['plumber.features.modifiers' => false]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->html();

    expect($html)
        ->not->toContain('data-testid="catalog-tab-modifiers"')
        ->not->toContain("wire:click=\"setTab('modifiers')\"")
        ->not->toContain('data-testid="catalog-modifier-modal"')
        ->not->toContain('data-testid="catalog-product-modifiers"');
});

it('shows catalog modifier UI when the feature flag is on', function () {
    config(['plumber.features.modifiers' => true]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->html();

    expect($html)
        ->toContain('data-testid="catalog-tab-modifiers"');
});

it('hides POS modifier checkboxes when the feature flag is off', function () {
    config(['plumber.features.modifiers' => false]);

    ProductModifier::query()->create([
        'name_en' => 'Test Mod',
        'price_ex_vat' => 2,
        'vat_rate' => 15,
        'is_active' => true,
    ]);

    $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
    $mod = ProductModifier::query()->firstOrFail();
    $product->modifiers()->sync([$mod->id]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(Terminal::class, ['branch' => $this->branch])
        ->call('selectCategory', $product->product_category_id)
        ->html();

    expect($html)->not->toContain('data-testid="pos-product-modifiers"');
});

it('ignores modifier selections on POS add when the feature flag is off', function () {
    config(['plumber.features.modifiers' => false]);

    $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
    $mod = ProductModifier::query()->create([
        'name_en' => 'Extra',
        'price_ex_vat' => 5,
        'vat_rate' => 15,
        'is_active' => true,
    ]);
    $product->modifiers()->sync([$mod->id]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(Terminal::class, ['branch' => $this->branch])
        ->set('selectedModifiers', [$product->id => [$mod->id]])
        ->call('addProduct', $product->id);

    $order = \App\Models\PosOrder::query()->latest('id')->firstOrFail();
    $line = $order->line_items[0] ?? null;

    expect($line)->not->toBeNull()
        ->and($line['modifier_ids'] ?? [])->toBe([])
        ->and((float) $line['unit_price_ex_vat'])->toBe((float) $product->price_ex_vat);
});

it('redirects modifiers tab query param to products when flag is off', function () {
    config(['plumber.features.modifiers' => false]);

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['tab' => 'modifiers'])
        ->test(MenuManager::class)
        ->assertSet('tab', 'products');
});