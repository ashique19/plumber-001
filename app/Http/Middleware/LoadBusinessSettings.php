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
        BusinessContext::clear();

        try {
            BusinessContext::set(BusinessSetting::primary()->fresh());
        } catch (\Throwable) {
            // Single-tenant app: allow boot without a row (install in progress).
        }

        return $next($request);
    }
}
