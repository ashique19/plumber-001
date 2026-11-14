<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Tolerant settings JSON for MySQL/MariaDB imports (JSON column, LONGTEXT, or double-encoded strings).
 */
final class BusinessSettingsPayload implements CastsAttributes
{
    /**
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = $value;
        for ($i = 0; $i < 3 && is_string($decoded); $i++) {
            $decoded = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_array($value)) {
            $value = [];
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}