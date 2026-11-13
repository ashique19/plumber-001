<?php

namespace App\Support;

final class TenantBusinessHours
{
    /**
     * Render legacy storefront hours rows from structured weekday settings.
     *
     * @param  list<array{key: string, closed: bool, open: string, close: string}>  $weekdays
     * @return list<array{label_en: string, label_ar: string, value_en: string, value_ar: string}>
     */
    public static function toLegacyRows(array $weekdays, ?string $note = null): array
    {
        $groups = [];
        foreach ($weekdays as $day) {
            $key = (string) ($day['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $closed = (bool) ($day['closed'] ?? false);
            $signature = $closed ? 'closed' : ($day['open'] ?? '').'-'.($day['close'] ?? '');
            $groups[$signature][] = $key;
        }

        $rows = [];
        foreach ($groups as $signature => $keys) {
            $labelEn = self::englishDayRange($keys);
            $labelAr = self::arabicDayRange($keys);
            if ($signature === 'closed') {
                $rows[] = [
                    'label_en' => $labelEn,
                    'label_ar' => $labelAr,
                    'value_en' => __('business.hours.closed', [], 'en'),
                    'value_ar' => __('business.hours.closed', [], 'ar'),
                ];
            } else {
                [$open, $close] = explode('-', $signature, 2);
                $rows[] = [
                    'label_en' => $labelEn,
                    'label_ar' => $labelAr,
                    'value_en' => self::format12h($open).' – '.self::format12h($close),
                    'value_ar' => self::format12h($open, true).' – '.self::format12h($close, true),
                ];
            }
        }

        if (filled($note)) {
            $rows[] = [
                'label_en' => __('business.hours.note', [], 'en'),
                'label_ar' => __('business.hours.note', [], 'ar'),
                'value_en' => $note,
                'value_ar' => $note,
            ];
        }

        return $rows;
    }

    /**
     * Best-effort import of admin weekday grid from legacy storefront hour rows (demo / seed).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, closed: bool, open: string, close: string}>|null
     */
    public static function weekdaysFromLegacyRows(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $byKey = [];
        foreach (TenantBusinessProfile::defaultWeekdays() as $day) {
            $byKey[$day['key']] = $day;
        }

        $changed = false;

        foreach ($rows as $row) {
            $label = strtolower((string) ($row['label_en'] ?? ''));
            $value = (string) ($row['value_en'] ?? '');

            if ($value === '' || str_contains($label, 'emergency') || str_contains($label, 'note')) {
                continue;
            }

            if (str_contains(strtolower($value), 'closed')) {
                foreach (self::dayKeysFromLabel($label) as $key) {
                    if (! isset($byKey[$key])) {
                        continue;
                    }
                    $byKey[$key] = ['key' => $key, 'closed' => true, 'open' => '00:00', 'close' => '00:00'];
                    $changed = true;
                }

                continue;
            }

            $range = self::parse12hRange($value);
            if ($range === null) {
                continue;
            }

            foreach (self::dayKeysFromLabel($label) as $key) {
                if (! isset($byKey[$key])) {
                    continue;
                }
                $byKey[$key] = [
                    'key' => $key,
                    'closed' => false,
                    'open' => $range[0],
                    'close' => $range[1],
                ];
                $changed = true;
            }
        }

        return $changed ? array_values($byKey) : null;
    }

    /**
     * @return list<string>
     */
    private static function dayKeysFromLabel(string $label): array
    {
        $order = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $short = [
            'sat' => 'saturday',
            'sun' => 'sunday',
            'mon' => 'monday',
            'tue' => 'tuesday',
            'wed' => 'wednesday',
            'thu' => 'thursday',
            'fri' => 'friday',
        ];

        if (str_contains($label, 'fri') && ! str_contains($label, 'sat')) {
            return ['friday'];
        }

        if (preg_match('/(sat|saturday).*(thu|thursday)/', $label)) {
            return ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
        }

        foreach ($short as $abbr => $full) {
            if (str_contains($label, $abbr)) {
                return [$full];
            }
        }

        return $order;
    }

    /**
     * @return array{0: string, 1: string}|null 24h open, close
     */
    private static function parse12hRange(string $value): ?array
    {
        if (! preg_match(
            '/(\d{1,2}):(\d{2})\s*(AM|PM)\s*[–\-—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)/iu',
            $value,
            $m,
        )) {
            return null;
        }

        return [
            self::to24h((int) $m[1], (int) $m[2], strtoupper($m[3])),
            self::to24h((int) $m[4], (int) $m[5], strtoupper($m[6])),
        ];
    }

    private static function to24h(int $hour, int $minute, string $ampm): string
    {
        if ($ampm === 'PM' && $hour < 12) {
            $hour += 12;
        }
        if ($ampm === 'AM' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * @param  list<string>  $keys
     */
    private static function englishDayRange(array $keys): string
    {
        $short = array_map(fn ($k) => ucfirst(substr($k, 0, 3)), $keys);
        if (count($short) <= 2) {
            return implode(' – ', $short);
        }

        return $short[0].' – '.end($short);
    }

    /**
     * @param  list<string>  $keys
     */
    private static function arabicDayRange(array $keys): string
    {
        $map = [
            'saturday' => 'السبت',
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
        ];
        $names = array_map(fn ($k) => $map[$k] ?? $k, $keys);
        if (count($names) <= 2) {
            return implode(' – ', $names);
        }

        return $names[0].' – '.end($names);
    }

    private static function format12h(string $time, bool $arabic = false): string
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }
        if ($arabic) {
            return sprintf('%d:%02d %s', $h12, $m, $ampm === 'AM' ? 'ص' : 'م');
        }

        return sprintf('%d:%02d %s', $h12, $m, $ampm);
    }
}
