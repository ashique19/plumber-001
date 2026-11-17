<?php

namespace App\Support\Expenses;

use App\Enums\BusinessExpenseCategory;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ExpenseCategoryCatalog
{
    public static function ready(): bool
    {
        return Schema::hasTable('business_expense_categories');
    }

    public static function ensureDefaults(): void
    {
        if (! self::ready()) {
            return;
        }

        foreach (BusinessExpenseCategory::cases() as $case) {
            ExpenseCategory::query()->firstOrCreate(
                ['slug' => $case->value],
                ['name' => (string) __("expenses.categories.{$case->value}", [], 'en')],
            );
        }
    }

    /**
     * @return list<string>
     */
    public static function allowedSlugs(): array
    {
        if (! self::ready()) {
            return BusinessExpenseCategory::values();
        }

        return ExpenseCategory::query()->orderBy('slug')->pluck('slug')->all();
    }

    public static function label(string $slug): string
    {
        if (! self::ready()) {
            return (string) __("expenses.categories.{$slug}", [], app()->getLocale());
        }

        $category = ExpenseCategory::query()->where('slug', $slug)->first();
        if ($category !== null) {
            return $category->name;
        }

        $translated = __("expenses.categories.{$slug}");

        return $translated !== "expenses.categories.{$slug}" ? $translated : $slug;
    }

    public static function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $suffix = 2;

        while (self::slugTaken($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return substr($slug, 0, 64);
    }

    private static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = ExpenseCategory::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}