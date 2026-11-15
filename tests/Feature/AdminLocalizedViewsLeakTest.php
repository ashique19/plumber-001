<?php

use App\Business\BusinessContext;
use App\Livewire\Catalog\MenuManager;
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
        app()->setLocale($locale);

        $dashboard = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($dashboard, "dashboard [{$locale}]");
        TranslationLeakScanner::assertLocaleHasNoEnglishSigningService($dashboard, $locale, "dashboard [{$locale}]");

        $business = $this->get(route('admin.business', ['locale' => $locale]))->assertOk()->getContent();
        TranslationLeakScanner::assertHtmlHasNoLeakedKeys($business, "business settings [{$locale}]");
        TranslationLeakScanner::assertBusinessPageHasNoEnglishLabels($business, $locale);
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

it('shows translated sidebar nav labels in bn and ar when admin sidebar is enabled', function () {
    config(['ui.admin_sidebar' => true]);

    $html = $this->get(route('admin.dashboard', ['locale' => 'bn']))->assertOk()->getContent();

    expect($html)->toContain(__('admin_nav.items.dashboard', [], 'bn'))
        ->and($html)->toContain(__('admin_nav.groups.products', [], 'bn'))
        ->and($html)->toContain(__('admin_nav.items.all_products', [], 'bn'))
        ->and($html)->toContain(__('admin_nav.items.settings', [], 'bn'));

    $html = $this->get(route('admin.dashboard', ['locale' => 'ar']))->assertOk()->getContent();
    expect($html)->toContain(__('admin_nav.groups.sales', [], 'ar'))
        ->and($html)->toContain(__('admin_nav.items.customers', [], 'ar'));
});

it('shows translated dashboard quick actions and business title in bn and ar', function () {
    config(['ui.admin_sidebar' => true]);

    $html = $this->get(route('admin.dashboard', ['locale' => 'bn']))->assertOk()->getContent();

    expect($html)->toContain(__('dashboard.tiles.add_product', [], 'bn'))
        ->and($html)->toContain(__('dashboard.quick_actions', [], 'bn'));

    $business = $this->get(route('admin.business', ['locale' => 'bn']))->assertOk()->getContent();
    expect($business)->toContain(__('business.title', [], 'bn'));

    $html = $this->get(route('admin.dashboard', ['locale' => 'ar']))->assertOk()->getContent();
    expect($html)->toContain(__('dashboard.tiles.add_product', [], 'ar'));

    $business = $this->get(route('admin.business', ['locale' => 'ar']))->assertOk()->getContent();
    expect($business)->toContain(__('business.title', [], 'ar'));
});