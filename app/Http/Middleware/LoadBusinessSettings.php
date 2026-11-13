<?php

namespace App\Http\Middleware;

use App\Business\BusinessContext;
use App\Models\BusinessSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadBusinessSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BusinessContext::get() === null) {
            $business = BusinessSetting::query()->find(1);
            if ($business !== null) {
                BusinessContext::set($business);
            }
        }

        return $next($request);
    }
}
