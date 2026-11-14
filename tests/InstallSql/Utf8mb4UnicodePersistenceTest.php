<?php

use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InstallSqlMysqlTestEnvironment;
use Tests\Support\InstallSqlSqlRunner;

afterEach(function () {
    Config::set('database.default', 'sqlite');
    DB::purge('mysql');
    DB::purge('sqlite');
});

beforeEach(function () {
    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; utf8mb4 persistence requires MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    InstallSqlSqlRunner::applyGlobPatches(base_path('docs/sql/2026-11-14-*.sql'));

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('persists bangla and arabic text after install.sql import and utf8mb4 conversion on mysql', function () {
    $collation = DB::selectOne(
        'SELECT TABLE_COLLATION AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        ['product_categories'],
    );
    expect($collation?->c)->toBe('utf8mb4_unicode_ci');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Unicode category')
        ->set('categoryNameAr', 'تصنيف يونيكود')
        ->set('categoryNameBn', 'ইউনিকোড বিভাগ')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $category = ProductCategory::query()->where('name_en', 'Unicode category')->firstOrFail();
    expect($category->name_ar)->toBe('تصنيف يونيكود')
        ->and($category->name_bn)->toBe('ইউনিকোড বিভাগ');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Unicode service')
        ->set('productNameAr', 'خدمة يونيكود')
        ->set('productNameBn', 'ইউনিকোড সেবা')
        ->set('productPriceInput', '100')
        ->set('productVatRate', 15)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::query()->where('name_en', 'Unicode service')->firstOrFail();
    expect($product->name_ar)->toBe('خدمة يونيكود')
        ->and($product->name_bn)->toBe('ইউনিকোড সেবা');

    $customer = Customer::query()->create([
        'name' => 'গ্রাহক العربية',
        'phone' => '0507000333',
        'email' => 'unicode.customer@example.test',
    ]);
    expect($customer->fresh()->name)->toBe('গ্রাহক العربية');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('displayNameEn', 'Pipe Works')
        ->set('displayNameAr', 'أنابيب الرياض')
        ->set('displayNameBn', 'পাইপ ওয়ার্ক্স')
        ->call('saveBrand')
        ->assertHasNoErrors();

    $business = BusinessSetting::primary()->fresh();
    expect(data_get($business->settings, 'business.brand.display_name_ar'))->toBe('أنابيب الرياض')
        ->and(data_get($business->settings, 'business.brand.display_name_bn'))->toBe('পাইপ ওয়ার্ক্স');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->assertSet('displayNameAr', 'أنابيب الرياض')
        ->assertSet('displayNameBn', 'পাইপ ওয়ার্ক্স');
});

it('repairs latin1 product_categories so bangla inserts succeed after utf8mb4 convert script', function () {
    DB::statement('ALTER TABLE `product_categories` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci');

    expect(fn () => ProductCategory::query()->create([
        'name_en' => 'Latin1 fail',
        'name_bn' => 'বাংলা',
    ]))->toThrow(Throwable::class);

    InstallSqlSqlRunner::execStatements(
        InstallSqlSqlRunner::databasePdo(),
        file_get_contents(base_path('docs/sql/2026-11-14-utf8mb4-convert.sql')) ?: '',
    );

    $category = ProductCategory::query()->create([
        'name_en' => 'Latin1 repaired',
        'name_bn' => 'বাংলা মেরামত',
        'name_ar' => 'إصلاح',
    ]);

    expect($category->fresh()->name_bn)->toBe('বাংলা মেরামত');
});