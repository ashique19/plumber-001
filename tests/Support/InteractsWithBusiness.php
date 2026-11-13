<?php

namespace Tests\Support;

use App\Business\BusinessContext;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\AuthRoleProvisioner;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Support\Facades\Hash;

trait InteractsWithBusiness
{
    protected function seedBusiness(string $name = 'Test Café'): BusinessSetting
    {
        $business = BusinessSetting::query()->create([
            'name' => $name,
            'settings' => PlumberThemePreset::settings($name, $name, 'ar'),
        ]);

        Branch::query()->create([
            'code' => 'main',
            'name' => 'Main Branch',
        ]);

        BusinessContext::set($business);

        return $business;
    }

    protected function actingAsBusiness(BusinessSetting $business, ?StaffUser $user = null): StaffUser
    {
        BusinessContext::set($business);
        AuthRoleProvisioner::seedStaffRoles();

        $user ??= StaffUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        if (! $user->hasRole('owner')) {
            $user->assignRole('owner');
        }

        test()->actingAs($user, 'staff');

        return $user;
    }
}
