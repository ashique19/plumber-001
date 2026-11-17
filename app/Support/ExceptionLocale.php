<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ExceptionLocale
{
    public static function bootstrap(Request $request): void
    {
        $requested = $request->query('locale');
        if (! is_string($requested) || $requested === '') {
            $requested = LocaleCookie::valueFromRequest($request);
        }
        if (! is_string($requested) || $requested === '') {
            try {
                $requested = $request->hasSession() ? $request->session()->get('locale') : null;
            } catch (\Throwable) {
                $requested = null;
            }
        }

        if (is_string($requested) && $requested !== '') {
            app()->setLocale(EnabledLocales::resolve($requested, app()->getLocale()));
        }
    }
}