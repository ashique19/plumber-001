<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthRoleProvisioner
{
    public static function seedStaffRoles(): void
    {
        $guard = 'staff';

        foreach ([
            'pos.access',
            'inventory.view',
            'inventory.manage',
            'invoices.issue',
            'reports.view',
            'staff.manage',
            'business.manage',
        ] as $name) {
            Permission::findOrCreate($name, $guard);
        }

        foreach (['owner', 'manager', 'cashier', 'accountant'] as $roleName) {
            Role::findOrCreate($roleName, $guard);
        }

        Role::findByName('owner', $guard)->syncPermissions(Permission::where('guard_name', $guard)->pluck('name')->all());
        Role::findByName('manager', $guard)->syncPermissions(['pos.access', 'inventory.view', 'inventory.manage', 'reports.view', 'staff.manage', 'business.manage']);
        Role::findByName('cashier', $guard)->syncPermissions(['pos.access']);
        Role::findByName('accountant', $guard)->syncPermissions(['invoices.issue', 'reports.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
