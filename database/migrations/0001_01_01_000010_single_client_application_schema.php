<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('settings');
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->string('referral_code', 32)->nullable();
            $table->foreignId('referred_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('points_balance')->default(0);
            $table->json('profile')->nullable();
            $table->timestamps();

            $table->unique('phone');
            $table->unique('referral_code');
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('name_bn', 120)->nullable();
            $table->string('icon_key', 32)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_bn')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('item_type', 16)->default('item');
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('name_bn', 160)->nullable();
            $table->string('image_url')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_bn')->nullable();
            $table->decimal('price_ex_vat', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_homepage')->default(false);
            $table->boolean('show_on_menu')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
            $table->index(['show_on_homepage', 'is_active']);
            $table->index(['show_on_menu', 'is_active']);
        });

        Schema::create('product_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->decimal('price_ex_vat', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_product_modifier', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_modifier_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_modifier_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('document_type', 32);
            $table->string('status', 32)->default('draft');
            $table->foreignId('referenced_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('icv')->nullable();
            $table->string('invoice_hash', 512)->nullable();
            $table->string('previous_invoice_hash', 512)->nullable();
            $table->longText('signed_xml')->nullable();
            $table->text('qr_tlv_base64')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'issued_at']);
        });

        Schema::create('invoice_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 32)->default('open');
            $table->string('payment_method', 32)->nullable();
            $table->decimal('subtotal_ex_vat', 12, 2)->default(0);
            $table->decimal('discount_ex_vat', 12, 2)->default(0);
            $table->decimal('promo_discount_ex_vat', 12, 2)->default(0);
            $table->decimal('loyalty_discount_ex_vat', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total_inc_vat', 12, 2)->default(0);
            $table->unsignedBigInteger('loyalty_points_redeemed')->default(0);
            $table->json('line_items');
            $table->json('metadata')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_credit_note_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->integer('points_delta');
            $table->decimal('monetary_value_ex_vat', 12, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_en', 160);
            $table->string('name_ar', 160)->nullable();
            $table->string('name_bn', 160)->nullable();
            $table->string('unit', 16)->default('g');
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('low_stock_threshold', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('reason', 32)->nullable();
            $table->decimal('quantity_delta', 12, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('pos_order_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('product_id');
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->unique(['recipe_id', 'ingredient_id']);
        });

        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_per_unit', 12, 3);
            $table->timestamps();
            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->decimal('opening_cash_float', 12, 2)->nullable();
            $table->decimal('closing_cash_counted', 12, 2)->nullable();
            $table->text('close_notes')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('business_settings_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('tab');
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'business_settings_audit_logs',
            'shifts',
            'recipe_ingredients',
            'recipes',
            'product_ingredients',
            'inventory_movements',
            'ingredients',
            'loyalty_transactions',
            'pos_orders',
            'invoice_audit_logs',
            'invoices',
            'product_product_modifier',
            'product_modifiers',
            'products',
            'product_categories',
            'customers',
            'branches',
            'password_reset_tokens',
            'sessions',
            'staff_users',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
