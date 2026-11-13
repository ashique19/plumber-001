<?php

use App\Enums\PosOrderStatus;
use App\Livewire\Pos\OrderHistory;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Pos\PosCheckoutService;
use App\Business\BusinessContext;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->branch = Branch::query()
        ->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();
    BusinessContext::set($this->tenant);

    $this->order = seedDemoPaidPosOrder();
});

afterEach(function () {
    BusinessContext::clear();
});

it('scopes refund open buttons to a single row target', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(OrderHistory::class, ['branch' => $this->branch])
        ->html();

    expect($html)->toContain('wire:target="openRefundModal('.$this->order->id.')"');
});

it('requires a reason and issues one credit note', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(OrderHistory::class, ['branch' => $this->branch])
        ->call('openRefundModal', $this->order->id)
        ->assertSet('showRefundModal', true)
        ->set('refundReason', 'Customer complaint — wrong drink')
        ->call('confirmRefund')
        ->assertHasNoErrors()
        ->assertSet('showRefundModal', false)
        ->assertDispatched('toast');

    $this->order->refresh();
    expect($this->order->status)->toBe(PosOrderStatus::Refunded)
        ->and($this->order->refund_credit_note_id)->not->toBeNull();

    $creditCount = Invoice::query()
        ->where('document_type', 'credit_note')
        ->count();

    expect($creditCount)->toBe(1);
});

it('is idempotent when refund is submitted twice', function () {
    $checkout = app(PosCheckoutService::class);
    $reason = 'Duplicate tap test';

    $first = $checkout->refund($this->order, $this->owner, $reason);
    $second = $checkout->refund($this->order->fresh(), $this->owner, $reason);

    expect($second->id)->toBe($first->id);

    $creditCount = Invoice::query()
        ->where('document_type', 'credit_note')
        ->count();

    expect($creditCount)->toBe(1);
});
