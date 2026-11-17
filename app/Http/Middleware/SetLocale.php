<?php

namespace App\Http\Middleware;

use App\Business\BusinessContext;
use App\Models\StaffUser;
use App\Support\EnabledLocales;
use App\Support\LocaleCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $staffUser = $request->user('staff');
        $userLocale = $staffUser instanceof StaffUser && filled($staffUser->locale ?? null)
            ? (string) $staffUser->locale
            : null;

        $requested = $request->query('locale')
            ?? $request->session()->get('locale')
            ?? $userLocale
            ?? BusinessContext::get()?->settings['default_locale']
            ?? config('locales.default', 'bn');

        $locale = EnabledLocales::resolve(is_string($requested) ? $requested : null);

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        Cookie::queue(Cookie::forever(LocaleCookie::NAME, $locale));

        return $next($request);
    }
}
