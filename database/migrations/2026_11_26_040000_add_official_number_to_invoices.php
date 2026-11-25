<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tax invoice number returned by Z-Heart (data.invoice_number).
     * Companion SQL: docs/sql/2026-11-26-invoice-official-number.sql
     */
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'official_number')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('official_number', 127)->nullable()->after('document_number');
                $table->unique('official_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'official_number')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropUnique(['official_number']);
                $table->dropColumn('official_number');
            });
        }
    }
};
