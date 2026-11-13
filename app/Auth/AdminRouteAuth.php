<?php

namespace App\Auth;

use App\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AdminRouteAuth
{
    public static function resolveAuthenticatedUser(Request $request): ?StaffUser
    {
        $guardUser = Auth::guard('staff')->user();

        return $guardUser instanceof StaffUser && $guardUser->is_active ? $guardUser : null;
    }
}
