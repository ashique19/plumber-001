<?php

use App\Business\BusinessContext;
use App\Enums\PosPaymentMethod;
use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Business\BusinessSettingsService;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use App\Support\AuthRoleProvisioner;
use App\Support\Pos\ReceiptThermalPresenter;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;
use Tests\Support\ZHeartFakeSeller;

beforeEach(function () {
    $this->withoutVite();
    AuthRoleProvisioner::seedStaffRoles();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

it('prints the Z-Heart seller with contact fields from business settings on the thermal and HTTP receipt', function () {
    ZHeartFakeSeller::$block = ZHeartFakeSeller::sample();
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $vat = '399999999900003';

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966501112233')
        ->set('email', 'dispatch@pipepros.test')
        ->call('saveContact')
        ->assertHasNoErrors();

    $business = $business->fresh();
    $settings = app(BusinessSettingsService::class)->ensureHydrated($business);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
    $paid->load(['branch', 'cashier']);
    $invoice = $paid->invoice()->firstOrFail();

    $this->actingAs($cashier, 'staff');

    $thermal = ReceiptThermalPresenter::compose($settings, $paid, $invoice, $branch);

    expect($thermal['seller_en'])->toBe('ZH Plumbing Placeholder LLC')
        ->and($thermal['phone'])->toBe('+966501112233')
        ->and($thermal['email'])->toBe('dispatch@pipepros.test')
        ->and($thermal['trn'])->toBe($vat);

    $response = $this->get(route('admin.pos.receipt', [$paid, 'locale' => 'en', 'layout' => 'receipt']));
    $response->assertOk();
    $response->assertSee('ZH Plumbing Placeholder LLC', false);
    $response->assertSee('+966501112233', false);
    $response->assertSee('dispatch@pipepros.test', false);
    $response->assertSee($vat, false);
});
