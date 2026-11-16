<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('name_bn', 160);
            $table->string('code', 16)->unique();
            $table->string('ubl_unit_code', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('measurement_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->after('product_category_id')->constrained('product_units')->nullOnDelete();
            $table->foreignId('product_size_id')->nullable()->after('product_unit_id')->constrained('product_sizes')->nullOnDelete();
            $table->char('vat_zatca_category', 1)->default('S')->after('vat_rate');
            $table->timestamp('archived_at')->nullable()->after('updated_at');
            $table->boolean('name_i18n_review')->default(false)->after('name_bn');
        });

        Schema::create('product_sku_counters', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->unsignedInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku_counters');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_size_id');
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['vat_zatca_category', 'archived_at', 'name_i18n_review']);
        });

        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_units');
    }
};