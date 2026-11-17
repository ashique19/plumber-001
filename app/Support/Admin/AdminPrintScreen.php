<?php

namespace App\Support\Admin;

final class AdminPrintScreen
{
    public static function withEmbed(string $url): string
    {
        return self::withQuery($url, ['embed' => '1']);
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    public static function withQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query(array_filter($params, fn ($v) => $v !== null && $v !== ''));
    }
}