<?php

namespace App\Support;

final class LocaleCookie
{
    public const NAME = 'app_locale';

    public static function valueFromRequest(?\Illuminate\Http\Request $request = null): ?string
    {
        $request ??= request();
        $cookie = $request->cookie(self::NAME);

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }

    public static function resolveLocale(?\Illuminate\Http\Request $request = null): ?string
    {
        $raw = self::valueFromRequest($request);

        return $raw !== null ? EnabledLocales::resolve($raw) : null;
    }
}