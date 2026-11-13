<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\AuthRoleProvisioner;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'owner@example.test');
        $password = (string) env('ADMIN_PASSWORD', 'ChangeMe!2026');

        AuthRoleProvisioner::seedStaffRoles();

        BusinessSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Demo Plumbing Co',
                'settings' => PlumberThemePreset::settings('Demo Plumbing Co', 'شركة السباكة التجريبية', 'ar'),
            ],
        );

        $branch = Branch::query()->firstOrCreate(
            ['code' => 'ruh-main'],
            ['name' => 'Riyadh Service Center', 'name_ar' => 'مركز الرياض'],
        );

        $owner = StaffUser::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Owner',
                'password' => Hash::make($password),
                'branch_id' => $branch->id,
                'is_active' => true,
            ],
        );

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }
    }
}
