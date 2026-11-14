<?php

use App\Business\BusinessContext;
use App\Livewire\Catalog\MenuManager;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;
use Tests\Support\TranslationLeakScanner;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->actingAs($this->owner, 'staff');
});

it('renders standard invoice issue buyer labels without raw translation keys', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $html = $this->get(route('admin.invoices.issue', ['mode' => 'standard']))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($html, "issue/standard [{$locale}]");
        expect($html)->not->toContain('business.fields.fields.');
    }
});

it('renders dashboard and business settings without raw translation keys', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        $dashboard = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($dashboard, "dashboard [{$locale}]");

        $business = $this->get(route('admin.business', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($business, "business settings [{$locale}]");
        TranslationLeakScanner::assertBusinessPageHasNoEnglishLabels($business, $locale);

        if (in_array($locale, ['bn', 'ar'], true)) {
            expect(strtolower($dashboard))->not->toContain('signing service');
        }
    }
});

it('renders catalog list and product/category modals without raw keys or English labels in bn and ar', function () {
  foreach (['bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $page = $this->get(route('admin.catalog', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($page, "catalog list [{$locale}]");
        TranslationLeakScanner::assertCatalogPageHasNoEnglishLabels($page, $locale);
        expect($page)->toContain(__('catalog.add_product', [], $locale));

        $withProductModal = Livewire::test(MenuManager::class)
            ->call('startProduct')
            ->html();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($withProductModal, "catalog product modal [{$locale}]");
        TranslationLeakScanner::assertCatalogPageHasNoEnglishLabels($withProductModal, $locale);
        expect($withProductModal)->toContain(__('catalog.save', [], $locale));

        $withCategoryModal = Livewire::test(MenuManager::class)
            ->call('startCategory')
            ->html();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($withCategoryModal, "catalog category modal [{$locale}]");
        TranslationLeakScanner::assertCatalogPageHasNoEnglishLabels($withCategoryModal, $locale);
        expect($withCategoryModal)->toContain(__('catalog.add_category', [], $locale));
    }
});

it('shows translated dashboard quick actions and settings nav in bn and ar', function () {
    config(['ui.admin_sidebar' => false]);

    $html = $this->get(route('admin.dashboard', ['locale' => 'bn']))->assertOk()->getContent();
    expect($html)->toContain(__('dashboard.add_products', [], 'bn'))
        ->and($html)->toContain(__('dashboard.quick_actions', [], 'bn'));

    $business = $this->get(route('admin.business', ['locale' => 'bn']))->assertOk()->getContent();
    expect($business)->toContain(__('business.title', [], 'bn'));

    $html = $this->get(route('admin.dashboard', ['locale' => 'ar']))->assertOk()->getContent();
    expect($html)->toContain(__('dashboard.add_products', [], 'ar'));

    $business = $this->get(route('admin.business', ['locale' => 'ar']))->assertOk()->getContent();
    expect($business)->toContain(__('business.title', [], 'ar'));
});