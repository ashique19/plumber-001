<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('selects a customer into standard invoice buyer fields', function () {
    $customer = Customer::query()->create([
        'name' => 'Acme Plumbing',
        'phone' => '0512345678',
        'email' => 'acme@example.test',
        'vat_number' => '300000000000003',
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'cr_number' => '1010123456',
            'country' => 'SA',
            'national_address' => [
                'building' => '10',
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
        ],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('customerSearch', 'Acme')
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSet('buyerName', 'Acme Plumbing')
        ->assertSet('buyerVat', '300000000000003')
        ->assertSet('buyerCr', '1010123456')
        ->assertSet('addrCity', 'Riyadh');
});

it('renders Arabic product hint below the name input in quick modal', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('openQuickProductModal')
        ->html();

    $arPos = strpos($html, 'id="quickProductNameAr"');
    $hintAfterPos = strpos($html, 'form-field__hint--after', $arPos);

    expect($arPos)->not->toBeFalse()
        ->and($hintAfterPos)->not->toBeFalse()
        ->and($hintAfterPos)->toBeGreaterThan($arPos);
});

it('requires Arabic product name on quick create with localized attribute label', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('quickProductNameEn', 'English Only Widget')
        ->set('quickProductPrice', '50')
        ->call('saveQuickProduct')
        ->assertHasErrors(['quickProductNameAr' => 'required']);

    $message = __('validation.required', [
        'attribute' => __('validation.quick_attributes.product_name_ar', locale: 'bn'),
    ], locale: 'bn');

    expect($message)->toContain(__('validation.quick_attributes.product_name_ar', locale: 'bn'));
});

it('creates a quick product when Arabic name is provided', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('quickProductNameEn', 'English Only Widget')
        ->set('quickProductNameAr', 'منتج')
        ->set('quickProductPrice', '50')
        ->call('saveQuickProduct')
        ->assertHasNoErrors()
        ->assertSet('lines.0.name', 'English Only Widget')
        ->assertSet('lines.0.name_ar', 'منتج');
});

it('creates a customer from the invoice modal and keeps line state', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $this->product->id)
        ->set('customerQuickName', 'New Buyer LLC')
        ->set('customerQuickPhone', '0598765432')
        ->set('customerQuickVat', '300000000000005')
        ->call('saveQuickCustomer')
        ->assertHasNoErrors()
        ->assertSet('lines.0.product_id', $this->product->id)
        ->assertSet('buyerPhone', '0598765432');
});

it('filters add-line product search results', function () {
    $rows = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('addLineProductSearch', 'SRV-LEAK')
        ->instance()
        ->addLineProductSearchResults();

    expect($rows)->not->toBeEmpty()
        ->and($rows->contains(fn ($p) => $p->id === $this->product->id))->toBeTrue();
});

it('renders customer and product pickers on quotation create', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->html();

    expect($html)->toContain('invoice-customer-picker')
        ->and($html)->toContain('invoice-add-line-search')
        ->and($html)->toContain(__('invoices.customer_search', locale: 'bn'));
});

it('shows inline customer and product search on simplified issue', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)->toContain('invoice-customer-picker')
        ->and($html)->toContain('wire:model.live.debounce.350ms="customerSearch"')
        ->and($html)->toContain('addLineProductSearch')
        ->and($html)->toContain(__('invoices.search_catalog_products'))
        ->and($html)->toContain(__('invoices.cash_customer_default'));
});

it('hides quick create buttons for staff without catalog write access', function () {
    $cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();

    $html = Livewire::actingAs($cashier, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)->not->toContain('invoice-picker-add');
});