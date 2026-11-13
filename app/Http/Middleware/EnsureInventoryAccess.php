<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null) {
            abort(403, 'Catalog access required.');
        }


        if (! $user->can('inventory.view')) {
            abort(403, 'Catalog access required.');
        }

        return $next($request);
    }
}
