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

beforeEach(function () {
    $this->withoutVite();
    AuthRoleProvisioner::seedStaffRoles();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

it('reflects legal and contact seller fields on thermal receipt and HTTP receipt', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $vat = '300000000000003';

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('legalNameEn', 'Riyadh Pipe Pros LLC')
        ->set('legalNameAr', 'شركة أنابيب الرياض')
        ->set('vatNumber', $vat)
        ->set('addressEn', 'King Fahd Rd, Riyadh')
        ->set('naBuilding', '7243')
        ->set('naStreet', 'King Fahd Rd')
        ->set('naDistrict', 'Al Olaya')
        ->set('naCity', 'Riyadh')
        ->set('naPostal', '12211')
        ->call('saveLegal')
        ->assertHasNoErrors();

    Livewire::test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966501112233')
        ->set('email', 'dispatch@pipepros.test')
        ->call('saveContact')
        ->assertHasNoErrors();

    $business = $business->fresh();
    $settings = app(BusinessSettingsService::class)->ensureHydrated($business);
    $legal = $settings->settings['legal_entity'] ?? [];

    expect($legal['name_en'])->toBe('Riyadh Pipe Pros LLC')
        ->and($legal['vat_number'])->toBe($vat)
        ->and($legal['address'])->toBe('King Fahd Rd, Riyadh');

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
    $paid->load(['branch', 'cashier']);
    $invoice = $paid->invoice()->firstOrFail();

    $this->actingAs($cashier, 'staff');

    $thermal = ReceiptThermalPresenter::compose($settings, $paid, $invoice, $branch);

    expect($thermal['seller_en'])->toBe('Riyadh Pipe Pros LLC')
        ->and($thermal['phone'])->toBe('+966501112233')
        ->and($thermal['email'])->toBe('dispatch@pipepros.test')
        ->and($thermal['trn'])->toBe($vat)
        ->and($legal['address'])->toBe('King Fahd Rd, Riyadh')
        ->and(collect($thermal['branch_lines'])->join(' '))->toContain('King Fahd Rd');

    $response = $this->get(route('admin.pos.receipt', [$paid, 'locale' => 'en']));
    $response->assertOk();
    $response->assertSee('Riyadh Pipe Pros LLC', false);
    $response->assertSee('+966501112233', false);
    $response->assertSee('dispatch@pipepros.test', false);
    $response->assertSee($vat, false);
    $response->assertSee('King Fahd Rd', false);
});
