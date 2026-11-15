<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotations')) {
            return;
        }

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quotation_number', 64)->unique();
            $table->string('invoice_mode', 16)->default('simplified');
            $table->date('validity_until');
            $table->json('payload');
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('created_by_staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['validity_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};