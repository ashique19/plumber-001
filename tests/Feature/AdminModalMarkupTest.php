<?php

use App\Business\BusinessContext;
use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Purchases\CreatePurchase;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

foreach (['en', 'bn', 'ar'] as $locale) {
    it("renders shared x-modal markup for quick supplier on record purchase ({$locale})", function () use ($locale) {
        app()->setLocale($locale);

        Livewire::actingAs($this->owner, 'staff')
            ->withQueryParams(['locale' => $locale])
            ->test(CreatePurchase::class)
            ->set('showQuickSupplierModal', true)
            ->assertSee(__('suppliers.quick_add_title', [], $locale), false)
            ->assertSeeHtml('x-modal-backdrop')
            ->assertSeeHtml('x-modal__header')
            ->assertSeeHtml('x-modal__footer')
            ->assertSeeHtml('x-modal-form__fields')
            ->assertSeeHtml(e(__('suppliers.save_and_select', [], $locale)))
            ->assertDontSeeHtml('backdrop-blur-sm');
    });
}

it('renders quick customer modal outside the invoice form', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('showQuickCustomerModal', true)
        ->html();

    $backdropPos = strpos($html, 'x-modal-backdrop');
    $modalFormPos = strpos($html, 'wire:submit.prevent="saveQuickCustomer"');
    expect($backdropPos)->toBeGreaterThan(0);
    expect($modalFormPos)->toBeGreaterThan(0);

    $issueFormClose = strrpos(substr($html, 0, $backdropPos), '</form>');
    expect($issueFormClose)->not->toBeFalse();
    expect($modalFormPos)->toBeGreaterThan($issueFormClose);
});

it('does not use legacy app-modal wrapper for quick supplier', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('showQuickSupplierModal', true)
        ->assertDontSeeHtml('app-modal-backdrop');
});