<?php

use App\Enums\BusinessExpenseCategory;
use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_expense_categories')) {
            Schema::create('business_expense_categories', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name', 120);
                $table->timestamps();
            });
        }

        foreach (BusinessExpenseCategory::cases() as $case) {
            ExpenseCategory::query()->firstOrCreate(
                ['slug' => $case->value],
                ['name' => (string) __("expenses.categories.{$case->value}", [], 'en')],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expense_categories');
    }
};