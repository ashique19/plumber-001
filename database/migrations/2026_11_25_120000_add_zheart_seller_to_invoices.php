<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller block returned by Z-Heart at signing (the seller on the signed invoice).
     * Companion SQL: docs/sql/2026-11-25-invoice-zheart-seller.sql
     */
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'zheart_seller')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->json('zheart_seller')->nullable()->after('qr_tlv_base64');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'zheart_seller')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('zheart_seller');
            });
        }
    }
};
