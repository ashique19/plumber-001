<?php

namespace App\Support;

use Spatie\Permission\PermissionRegistrar;

final class PermissionTeams
{
    public static function forBusinessConsole(?PermissionRegistrar $registrar = null): void
    {
        ($registrar ?? app(PermissionRegistrar::class))
            ->setPermissionsTeamId(1);
    }

    public static function forBusiness(int $businessId, ?PermissionRegistrar $registrar = null): void
    {
        ($registrar ?? app(PermissionRegistrar::class))
            ->setPermissionsTeamId($businessId);
    }

}
