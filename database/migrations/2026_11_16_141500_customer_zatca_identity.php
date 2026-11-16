<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'legal_name_ar')) {
                $table->string('legal_name_ar', 160)->nullable()->after('name');
            }
            if (! Schema::hasColumn('customers', 'legal_name_en')) {
                $table->string('legal_name_en', 160)->nullable()->after('legal_name_ar');
            }
            if (! Schema::hasColumn('customers', 'vat_registered')) {
                $table->boolean('vat_registered')->default(false)->after('vat_number');
            }
            if (! Schema::hasColumn('customers', 'alt_id_type')) {
                $table->string('alt_id_type', 8)->nullable()->after('vat_registered');
            }
            if (! Schema::hasColumn('customers', 'alt_id_number')) {
                $table->string('alt_id_number', 64)->nullable()->after('alt_id_type');
            }
            if (! Schema::hasColumn('customers', 'country_code')) {
                $table->char('country_code', 2)->default('SA')->after('alt_id_number');
            }
            if (! Schema::hasColumn('customers', 'street_name')) {
                $table->string('street_name', 120)->nullable()->after('country_code');
            }
            if (! Schema::hasColumn('customers', 'city')) {
                $table->string('city', 80)->nullable()->after('street_name');
            }
        });

        DB::table('customers')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $profile = is_string($row->profile) ? json_decode($row->profile, true) : (array) json_decode((string) $row->profile, true);
                $profile = is_array($profile) ? $profile : [];
                $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];
                $vat = trim((string) ($row->vat_number ?? ''));
                $cr = trim((string) ($profile['cr_number'] ?? ''));
                $country = strtoupper(trim((string) ($profile['country'] ?? ($na['country'] ?? 'SA')))) ?: 'SA';

                $updates = [
                    'legal_name_en' => trim((string) $row->name),
                    'legal_name_ar' => filled($profile['name_ar'] ?? null) ? trim((string) $profile['name_ar']) : null,
                    'vat_registered' => $vat !== '',
                    'country_code' => $country,
                    'street_name' => filled($na['street'] ?? null) ? trim((string) $na['street']) : null,
                    'city' => filled($na['city'] ?? null) ? trim((string) $na['city']) : null,
                    'updated_at' => now(),
                ];

                if ($vat === '' && $cr !== '') {
                    $updates['alt_id_type'] = 'CRN';
                    $updates['alt_id_number'] = $cr;
                }

                DB::table('customers')->where('id', $row->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            foreach (['city', 'street_name', 'country_code', 'alt_id_number', 'alt_id_type', 'vat_registered', 'legal_name_en', 'legal_name_ar'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};