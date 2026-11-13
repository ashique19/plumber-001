<?php

namespace App\Support;

use Spatie\Permission\PermissionRegistrar;

final class PermissionTeams
{
    public static function forPlatform(?PermissionRegistrar $registrar = null): void
    {
        ($registrar ?? app(PermissionRegistrar::class))
            ->setPermissionsTeamId(PlatformPermissionTeam::TENANT_ID);
    }

    public static function forTenant(int $tenantId, ?PermissionRegistrar $registrar = null): void
    {
        ($registrar ?? app(PermissionRegistrar::class))
            ->setPermissionsTeamId($tenantId);
    }

}
