<?php

namespace App\Support\Invoicing;

final class ZatcaResponseSanitizer
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'otp',
        'csid',
        'secret',
        'password',
        'token',
        'api_key',
        'apikey',
        'private',
        'certificate',
        'pem',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function sanitizeForDisplay(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        return self::walk($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function walk(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || self::isSensitiveKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::walk($value);
                if ($nested !== []) {
                    $out[$key] = $nested;
                }

                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                $out[$key] = mb_substr(SigningFailureSanitizer::sanitizeMessage($trimmed), 0, 500);

                continue;
            }

            if (is_int($value) || is_float($value) || is_bool($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}