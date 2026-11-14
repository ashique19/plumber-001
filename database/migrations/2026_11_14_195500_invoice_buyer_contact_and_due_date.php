<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standard manual issue stores buyer phone, email, country, and due date hint in
 * invoices.payload (buyer_phone, buyer_email, buyer_country, buyer_due_date).
 * invoices.due_date is the canonical due date column for print and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'due_date')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->date('due_date')->nullable()->after('issued_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'due_date')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('due_date');
        });
    }
};