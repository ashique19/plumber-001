<?php

use App\Livewire\Expenses\ExpensesHub;
use App\Models\BusinessExpense;
use App\Models\BusinessSetting;
use App\Models\ExpenseCategory;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('opens categories modal for managers with staff.manage', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->assertSee(__('expenses.manage_categories'), false)
        ->call('openCategoriesModal')
        ->assertSet('showCategoriesModal', true)
        ->assertSee(__('expenses.categories_modal.title'), false);
});

it('adds and renames a category and refreshes dropdown options', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->call('openCategoriesModal')
        ->set('newCategoryName', 'Workshop tools')
        ->call('addCategory')
        ->assertHasNoErrors();

    $created = ExpenseCategory::query()->where('name', 'Workshop tools')->first();
    expect($created)->not->toBeNull();

    $component
        ->call('startEditCategory', $created->id)
        ->set('editingCategoryName', 'Tools')
        ->call('saveCategoryRename')
        ->assertHasNoErrors();

    expect(ExpenseCategory::query()->whereKey($created->id)->value('name'))->toBe('Tools');

    $component
        ->call('closeCategoriesModal')
        ->set('showForm', true)
        ->assertSee('Tools', false);
});

it('blocks deleting a category in use until merge target is chosen', function () {
    $cat = ExpenseCategory::query()->where('slug', 'supplies')->firstOrFail();
    $other = ExpenseCategory::query()->where('slug', 'other')->firstOrFail();

    BusinessExpense::query()->create([
        'spent_on' => now()->toDateString(),
        'category' => $cat->slug,
        'amount_ex_vat' => 25,
        'recorded_by_staff_user_id' => $this->owner->id,
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->call('openCategoriesModal')
        ->call('promptDeleteCategory', $cat->id)
        ->call('deleteCategory')
        ->assertHasErrors(['mergeCategorySlug'])
        ->set('mergeCategorySlug', $other->slug)
        ->call('deleteCategory')
        ->assertHasNoErrors();

    expect(ExpenseCategory::query()->whereKey($cat->id)->exists())->toBeFalse();
    expect(BusinessExpense::query()->where('category', $other->slug)->count())->toBe(1);
});

it('allows deleting an unused category without merge', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->call('openCategoriesModal')
        ->set('newCategoryName', 'Disposable')
        ->call('addCategory');

    $cat = ExpenseCategory::query()->where('name', 'Disposable')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->call('openCategoriesModal')
        ->call('promptDeleteCategory', $cat->id)
        ->call('deleteCategory')
        ->assertHasNoErrors();

    expect(ExpenseCategory::query()->whereKey($cat->id)->exists())->toBeFalse();
});