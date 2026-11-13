<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null) {
            abort(403, 'POS access required.');
        }


        if (! $user->can('pos.access')) {
            abort(403, 'POS access required.');
        }

        return $next($request);
    }
}
