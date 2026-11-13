<?php

namespace App\Support\Reconcile;

use Illuminate\Support\Carbon;

final class ReconcileIssueDateFilter
{
    public const BUSINESS_TZ = 'Asia/Riyadh';

    /**
     * @return array{0: Carbon, 1: Carbon}|null UTC bounds for issued_at query
     */
    public static function utcRangeForPreset(string $preset, ?Carbon $now = null): ?array
    {
        if ($preset === '' || $preset === 'all') {
            return null;
        }

        $now ??= Carbon::now(self::BUSINESS_TZ);

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            default => [null, null],
        };

        if ($start === null || $end === null) {
            return null;
        }

        return [$start->copy()->utc(), $end->copy()->utc()];
    }
}
