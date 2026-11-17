<?php

namespace App\Support\Staff;

use Illuminate\Support\Collection;

final class StaffAdminRoleCatalog
{
    public const UI_OWNER = 'owner';

    public const UI_MANAGER = 'manager';

    /** Back-office staff (maps to Spatie role `cashier`). */
    public const UI_STAFF = 'staff';

    /**
     * @return list<string>
     */
    public static function assignableUiRoles(): array
    {
        return [self::UI_OWNER, self::UI_MANAGER, self::UI_STAFF];
    }

    public static function toSpatieRole(string $uiRole): string
    {
        return match ($uiRole) {
            self::UI_STAFF => 'cashier',
            self::UI_OWNER, self::UI_MANAGER => $uiRole,
            default => 'cashier',
        };
    }

    /**
     * @param  Collection<int, string>|list<string>  $spatieRoleNames
     */
    public static function primaryUiRole(Collection|array $spatieRoleNames): string
    {
        $names = collect($spatieRoleNames)->map(fn ($n) => (string) $n)->all();

        foreach ([self::UI_OWNER, self::UI_MANAGER] as $priority) {
            if (in_array($priority, $names, true)) {
                return $priority;
            }
        }

        if (in_array('cashier', $names, true) || in_array('accountant', $names, true)) {
            return self::UI_STAFF;
        }

        return self::UI_STAFF;
    }

    public static function spatieRolesForUiRole(string $uiRole): string
    {
        return self::toSpatieRole($uiRole);
    }
}