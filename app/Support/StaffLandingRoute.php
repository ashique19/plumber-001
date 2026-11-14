<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\StaffUser;

final class StaffLandingRoute
{
    public static function forUser(BusinessSetting $business, StaffUser $user): string
    {
        if ($user->can('reports.view')) {
            return route('admin.dashboard');
        }

        if ($user->can('pos.access')) {
            return StaffBranchContext::posHomeUrl($user);
        }

        return route('admin.business');
    }
}
