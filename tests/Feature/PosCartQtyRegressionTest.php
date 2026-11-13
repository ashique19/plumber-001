<?php

use App\Business\BusinessContext;
use App\Models\PosOrder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('supports add then increment scenarios without removing the line', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $component = Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('incrementLine', 0);

    $orderId = (int) $component->get('orderId');
    $order = PosOrder::query()->findOrFail($orderId);
    expect((float) $order->line_items[0]['quantity'])->toBe(2.0);

    $component
        ->call('applyLineQuantity', 0, 5)
        ->call('incrementLine', 0);

    expect((float) $order->fresh()->line_items[0]['quantity'])->toBe(6.0);

    $component
        ->call('applyLineQuantity', 0, 1)
        ->call('applyLineQuantity', 0, 1)
        ->call('incrementLine', 0);

    expect((float) $order->fresh()->line_items[0]['quantity'])->toBe(2.0);

    $component
        ->call('applyLineQuantity', 0, 1)
        ->call('incrementLine', 0)
        ->call('incrementLine', 0);

    expect((float) $order->fresh()->line_items[0]['quantity'])->toBe(3.0);
});

it('ignores blur apply at zero without removing the cart line', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->call('applyLineQuantity', 0, 0)
        ->assertSet('undoLineSnapshot', null);

    expect(PosOrder::query()->latest('id')->first()->line_items)->toHaveCount(1);
});
