<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'track_stock')) {
                    $table->boolean('track_stock')->default(false)->after('show_on_menu');
                }
                if (! Schema::hasColumn('products', 'stock_quantity')) {
                    $table->decimal('stock_quantity', 12, 3)->default(0)->after('track_stock');
                }
                if (! Schema::hasColumn('products', 'low_stock_threshold')) {
                    $table->decimal('low_stock_threshold', 12, 3)->default(0)->after('stock_quantity');
                }
                if (! Schema::hasColumn('products', 'cost_ex_vat')) {
                    $table->decimal('cost_ex_vat', 12, 2)->default(0)->after('low_stock_threshold');
                }
            });
        }

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 160);
                $table->string('vat_number', 32)->nullable();
                $table->string('phone', 32)->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'name']);
            });
        }

        if (! Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('purchase_number', 64)->unique();
                $table->timestamp('purchased_at');
                $table->text('notes')->nullable();
                $table->foreignId('created_by_staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_id', 'purchased_at']);
            });
        }

        if (! Schema::hasTable('purchase_lines')) {
            Schema::create('purchase_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_cost_ex_vat', 12, 2);
                $table->decimal('vat_rate', 5, 2)->default(15);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_stock_movements')) {
            Schema::create('product_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('type', 32);
                $table->decimal('quantity_delta', 12, 3);
                $table->decimal('unit_cost_ex_vat', 12, 2)->nullable();
                $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('staff_users')->nullOnDelete();
                $table->string('reason', 160)->nullable();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
                $table->index(['invoice_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach (['cost_ex_vat', 'low_stock_threshold', 'stock_quantity', 'track_stock'] as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
