<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'cost_ex_vat')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `products` MODIFY `cost_ex_vat` DECIMAL(12,2) NULL DEFAULT NULL');
        } elseif ($driver === 'sqlite') {
            $this->makeProductsCostExVatNullableSqlite();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'cost_ex_vat')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('UPDATE `products` SET `cost_ex_vat` = 0 WHERE `cost_ex_vat` IS NULL');
            DB::statement('ALTER TABLE `products` MODIFY `cost_ex_vat` DECIMAL(12,2) NOT NULL DEFAULT 0');
        } elseif ($driver === 'sqlite') {
            $this->makeProductsCostExVatNotNullSqlite();
        }
    }

    private function makeProductsCostExVatNullableSqlite(): void
    {
        if (! Schema::hasColumn('products', 'cost_ex_vat_new')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('cost_ex_vat_new', 12, 2)->nullable();
            });
        }

        DB::table('products')->update([
            'cost_ex_vat_new' => DB::raw('cost_ex_vat'),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_ex_vat');
        });

        DB::statement('ALTER TABLE products RENAME COLUMN cost_ex_vat_new TO cost_ex_vat');
    }

    private function makeProductsCostExVatNotNullSqlite(): void
    {
        DB::table('products')->whereNull('cost_ex_vat')->update(['cost_ex_vat' => 0]);

        if (! Schema::hasColumn('products', 'cost_ex_vat_new')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('cost_ex_vat_new', 12, 2)->default(0);
            });
        }

        DB::table('products')->update([
            'cost_ex_vat_new' => DB::raw('COALESCE(cost_ex_vat, 0)'),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_ex_vat');
        });

        DB::statement('ALTER TABLE products RENAME COLUMN cost_ex_vat_new TO cost_ex_vat');
    }
};