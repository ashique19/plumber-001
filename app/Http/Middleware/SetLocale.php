<?php

namespace App\Http\Middleware;

use App\Support\EnabledLocales;
use App\Business\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('locale')
            ?? $request->session()->get('locale')
            ?? BusinessContext::get()?->settings['default_locale']
            ?? config('locales.default', 'en');

        $locale = EnabledLocales::resolve(is_string($requested) ? $requested : null);

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
