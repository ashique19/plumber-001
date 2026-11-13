<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\StaffUser;

final class TenantLandingRoute
{
    public static function forUser(BusinessSetting $business, StaffUser $user): string
    {
        if ($user->can('pos.access')) {
            return route('admin.pos.home');
        }

        if ($user->can('reports.view')) {
            return route('admin.dashboard');
        }

        return route('admin.business');
    }
}
