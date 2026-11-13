<?php

use App\Models\PosOrder;
use App\Services\Pos\PosOrderService;
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

it('increments and decrements line quantity through the order service', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    /** @var PosOrderService $orders */
    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $order = $order->fresh();

    expect((float) ($order->line_items[0]['quantity'] ?? 0))->toBe(1.0);

    $result = $orders->changeLineQuantity($order, 0, 1);
    $order = $result['order']->fresh();
    expect((float) $order->line_items[0]['quantity'])->toBe(2.0)
        ->and((float) $order->subtotal_ex_vat)->toBe(40.0)
        ->and((float) $order->vat_total)->toBe(6.0);

    $result = $orders->changeLineQuantity($order, 0, -1);
    $order = $result['order']->fresh();
    expect((float) $order->line_items[0]['quantity'])->toBe(1.0)
        ->and((float) $order->subtotal_ex_vat)->toBe(20.0);
});

it('removes a line when quantity is set to zero', function () {
      ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 2);
    $result = $orders->setLineQuantity($order->fresh(), 0, 0);
    $order = $result['order'];

    expect($order->line_items)->toBe([])
        ->and((float) $order->subtotal_ex_vat)->toBe(0.0)
        ->and((float) $order->total_inc_vat)->toBe(0.0);
});

it('recomputes totals when livewire increments and decrements cart lines', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $component = Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('incrementLine', 0)
        ->assertSet('undoLineSnapshot', null);

    $orderId = (int) $component->get('orderId');
    $order = PosOrder::query()->findOrFail($orderId);

    expect((float) $order->line_items[0]['quantity'])->toBe(2.0)
        ->and((float) $order->subtotal_ex_vat)->toBe(40.0);

    $component->call('decrementLine', 0);

    $order = $order->fresh();
    expect((float) $order->line_items[0]['quantity'])->toBe(1.0)
        ->and((float) $order->subtotal_ex_vat)->toBe(20.0);
});

it('captures undo snapshot when decrement removes last unit', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $order = $order->fresh();

    Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->set('orderId', $order->id)
        ->call('decrementLine', 0)
        ->assertSet('undoLineSnapshot.line.name', 'Latte');

    expect($order->fresh()->line_items)->toBe([]);
});

it('restores a removed line via undo', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $order = $order->fresh();

    Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->set('orderId', $order->id)
        ->call('removeLine', 0)
        ->call('undoRemoveLine')
        ->assertSet('undoLineSnapshot', null);

    expect($order->fresh()->line_items)->toHaveCount(1)
        ->and($order->fresh()->line_items[0]['name'])->toBe('Latte');
});

it('renders quantity stepper controls on the cart panel', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $html = Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->html();

    expect($html)
        ->toContain('pos-qty-stepper')
        ->toContain('incrementLine')
        ->toContain('decrementLine')
        ->toContain('posQtyStepper');
});

it('never throws when applyLineQuantity receives invalid or edge values', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $component = Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id);

    $component
        ->call('applyLineQuantity', 99, 2)
        ->call('applyLineQuantity', 0, 0)
        ->call('applyLineQuantity', 0, 1500)
        ->call('applyLineQuantity', 0, 'oops')
        ->assertStatus(200);

    expect($component->errors())->toBeEmpty();
});

it('removes line when remove control is used', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('removeLine', 0)
        ->assertSet('undoLineSnapshot.line.name', 'Latte');

    expect(PosOrder::query()->latest('id')->first()->line_items)->toBe([]);
});
