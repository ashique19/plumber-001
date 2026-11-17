<?php

namespace App\Support\Invoicing;

use Illuminate\Http\Request;

final class PrintScreenBackUrl
{
    public const RETURN_PARAM = 'return';

    /** @var list<string> */
    public const INVOICE_HUB_KEYS = ['lifecycle', 'zatcaTab', 'typeFilter'];

    /** @var list<string> */
    public const QUOTATION_HUB_KEYS = ['lifecycle'];

    public static function invoiceHub(Request $request): string
    {
        return self::resolve($request, 'admin.invoices', '/admin/invoices', self::INVOICE_HUB_KEYS);
    }

    public static function quotationHub(Request $request): string
    {
        return self::resolve($request, 'admin.quotations', '/admin/quotations', self::QUOTATION_HUB_KEYS);
    }

    /**
     * @param  array<string, scalar|null>  $state
     * @param  list<string>  $allowedKeys
     */
    public static function encodeHubState(array $state, array $allowedKeys): ?string
    {
        $params = self::filterParams($state, $allowedKeys);

        return $params === [] ? null : http_build_query($params);
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    public static function listUrlFromEncodedReturn(?string $returnQuery, string $routeName, array $allowedKeys): string
    {
        $index = route($routeName);

        if ($returnQuery === null || trim($returnQuery) === '') {
            return $index;
        }

        parse_str($returnQuery, $params);
        $safe = self::filterParams($params, $allowedKeys);

        return $safe === [] ? $index : $index.'?'.http_build_query($safe);
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private static function resolve(Request $request, string $routeName, string $pathPrefix, array $allowedKeys): string
    {
        $index = route($routeName);

        $fromReturn = self::fromReturnParam($request->query(self::RETURN_PARAM), $index, $pathPrefix, $allowedKeys);
        if ($fromReturn !== null) {
            return $fromReturn;
        }

        return self::fromReferer($request, $index, $pathPrefix, $allowedKeys) ?? $index;
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private static function fromReturnParam(mixed $return, string $index, string $pathPrefix, array $allowedKeys): ?string
    {
        if (! is_string($return) || trim($return) === '') {
            return null;
        }

        $return = trim($return);

        if (str_starts_with($return, '/')) {
            return self::sanitizePath($return, $pathPrefix, $allowedKeys, $index);
        }

        parse_str($return, $params);
        $safe = self::filterParams($params, $allowedKeys);

        return $safe === [] ? $index : $index.'?'.http_build_query($safe);
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private static function fromReferer(Request $request, string $index, string $pathPrefix, array $allowedKeys): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        try {
            $url = parse_url($referer);
        } catch (\Throwable) {
            return null;
        }

        $path = (string) ($url['path'] ?? '');
        if (! str_starts_with($path, $pathPrefix)) {
            return null;
        }

        $query = (string) ($url['query'] ?? '');
        if ($query === '') {
            return $index;
        }

        parse_str($query, $params);
        $safe = self::filterParams($params, $allowedKeys);

        return $safe === [] ? $index : $index.'?'.http_build_query($safe);
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private static function sanitizePath(string $path, string $pathPrefix, array $allowedKeys, string $index): string
    {
        if (! str_starts_with($path, $pathPrefix)) {
            return $index;
        }

        $query = parse_url($path, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return $index;
        }

        parse_str($query, $params);
        $safe = self::filterParams($params, $allowedKeys);

        return $safe === [] ? $index : $index.'?'.http_build_query($safe);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>  $allowedKeys
     * @return array<string, string>
     */
    private static function filterParams(array $params, array $allowedKeys): array
    {
        $out = [];
        foreach ($allowedKeys as $key) {
            if (! array_key_exists($key, $params)) {
                continue;
            }
            $value = $params[$key];
            if (! is_scalar($value)) {
                continue;
            }
            $string = trim((string) $value);
            if ($string === '' || $string === 'all') {
                continue;
            }
            $out[$key] = $string;
        }

        return $out;
    }
}