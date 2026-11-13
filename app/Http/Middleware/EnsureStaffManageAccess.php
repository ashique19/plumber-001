<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffManageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null) {
            abort(403, 'Staff management required.');
        }


        if (! $user->can('staff.manage')) {
            abort(403, 'Staff management required.');
        }

        return $next($request);
    }
}
