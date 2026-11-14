<?php

use App\Livewire\Catalog\MenuManager;
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
        $this->markTestSkipped('MySQL is not reachable; category persistence requires MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    InstallSqlSqlRunner::applyGlobPatches(base_path('docs/sql/2026-11-14-*.sql'));

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('creates and updates a category with english and bangla only on install.sql mysql', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Install SQL Category')
        ->set('categoryNameBn', 'ইনস্টল এসকিউএল')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $category = ProductCategory::query()->where('name_en', 'Install SQL Category')->firstOrFail();
    expect($category->name_bn)->toBe('ইনস্টল এসকিউএল')
        ->and($category->name_ar)->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory', $category->id)
        ->set('categoryNameBn', 'আপডেট')
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect($category->fresh()->name_bn)->toBe('আপডেট');
});