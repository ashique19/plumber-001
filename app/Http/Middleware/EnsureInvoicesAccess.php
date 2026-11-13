<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureInvoicesAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null) {
            abort(403, 'Invoice access required.');
        }


        if (! $user->can('invoices.issue') && ! $user->can('reports.view')) {
            abort(403, 'Invoice access required.');
        }

        return $next($request);
    }
}
