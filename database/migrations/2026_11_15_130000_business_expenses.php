<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_expenses')) {
            return;
        }

        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->string('category', 64);
            $table->string('vendor', 255)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount_ex_vat', 12, 2);
            $table->foreignId('recorded_by_staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['spent_on', 'category']);
            $table->index(['branch_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};