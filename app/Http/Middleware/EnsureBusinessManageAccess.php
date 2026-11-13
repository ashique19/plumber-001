<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessManageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null) {
            abort(403, 'Business settings access required.');
        }


        if (! $user->can('business.manage')) {
            abort(403, 'Business settings access required.');
        }

        return $next($request);
    }
}
