<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfBusinessGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('staff')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
