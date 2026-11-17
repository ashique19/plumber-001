<?php

namespace App\Http\Middleware;

use App\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if (! $user instanceof StaffUser || ! $user->hasRole('owner')) {
            abort(403, 'Owner access required.');
        }

        return $next($request);
    }
}