<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'name_ar')) {
                $table->string('name_ar', 160)->nullable()->after('name');
            }
            if (! Schema::hasColumn('suppliers', 'contact_person')) {
                $table->string('contact_person', 120)->nullable()->after('name_ar');
            }
            if (! Schema::hasColumn('suppliers', 'email')) {
                $table->string('email', 120)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('suppliers', 'cr_number')) {
                $table->string('cr_number', 32)->nullable()->after('vat_number');
            }
            if (! Schema::hasColumn('suppliers', 'address')) {
                $table->text('address')->nullable()->after('cr_number');
            }
            if (! Schema::hasColumn('suppliers', 'city')) {
                $table->string('city', 80)->nullable()->after('address');
            }
            if (! Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable()->after('city');
            }
            if (! Schema::hasColumn('suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table): void {
            foreach (['name_ar', 'contact_person', 'email', 'cr_number', 'address', 'city', 'notes', 'is_active'] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};