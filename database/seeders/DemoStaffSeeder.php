<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
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
                'settings' => PlumberThemePreset::settings('Demo Plumbing Co', 'شركة السباكة التجريبية', 'ar'),
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

        Customer::query()->firstOrCreate(
            ['phone' => '0501000001'],
            [
                'name' => 'Regular Customer',
                'referral_code' => 'PLUMB100',
                'points_balance' => 120,
            ],
        );
    }
}
