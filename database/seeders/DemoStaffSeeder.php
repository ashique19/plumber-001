<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\AuthRoleProvisioner;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Rich demo dataset for tests and local demos (plumber single business).
 *
 * @deprecated Use {@see DatabaseSeeder}; kept for existing test imports.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        AuthRoleProvisioner::seedStaffRoles();

        BusinessSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Demo Plumbing Co',
                'settings' => PlumberThemePreset::settings('Demo Plumbing Co', 'شركة السباكة التجريبية', 'bn'),
            ],
        );

        $jad = Branch::query()->firstOrCreate(
            ['code' => 'jad-main'],
            ['name' => 'Jeddah Main', 'name_ar' => 'جدة الرئيسي'],
        );

        Branch::query()->firstOrCreate(
            ['code' => 'ruh-main'],
            ['name' => 'Riyadh Service Center', 'name_ar' => 'مركز الرياض'],
        );

        StaffUser::query()->firstOrCreate(
            ['email' => 'owner@example.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('TestPassword!2026'),
                'branch_id' => $jad->id,
                'is_active' => true,
            ],
        )->assignRole('owner');

        StaffUser::query()->firstOrCreate(
            ['email' => 'cashier@example.test'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('TestPassword!2026'),
                'branch_id' => $jad->id,
                'is_active' => true,
            ],
        )->assignRole('cashier');

        $this->call(PlumberCatalogSeeder::class);
        $this->call(CatalogUnitsSizesSeeder::class);

        Customer::query()->firstOrCreate(
            ['phone' => '0501000001'],
            [
                'name' => 'Regular Customer',
                'referral_code' => 'PLUMB100',
                'points_balance' => 120,
            ],
        );

        $owner = StaffUser::query()->where('email', 'owner@example.test')->first();
        if ($owner) {
            Quotation::query()->firstOrCreate(
                ['quotation_number' => 'QUO-DEMO-001'],
                [
                    'branch_id' => $jad->id,
                    'invoice_mode' => 'simplified',
                    'validity_until' => now()->addDays(30)->toDateString(),
                    'payload' => [
                        'lines' => [
                            ['name' => 'Demo pipe fitting', 'quantity' => 1, 'unit_price_ex_vat' => 85, 'vat_rate' => 15],
                        ],
                    ],
                    'created_by_staff_user_id' => $owner->id,
                ],
            );
        }
    }
}
