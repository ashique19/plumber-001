<?php

namespace App\Support\Hub;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;

final class HubQuotationLifecycleTab
{
    public const ALL = 'all';

    public const OPEN = 'open';

    public const CONVERTED = 'converted';

    public const EXPIRED = 'expired';

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [self::ALL, self::OPEN, self::CONVERTED, self::EXPIRED];
    }

    public static function normalize(string $tab): string
    {
        return in_array($tab, self::allowed(), true) ? $tab : self::ALL;
    }

    public static function applyScope(Builder $query, string $tab): void
    {
        $tab = self::normalize($tab);

        match ($tab) {
            self::OPEN => $query
                ->whereNull('converted_invoice_id')
                ->whereDate('validity_until', '>=', now('Asia/Riyadh')->toDateString()),
            self::CONVERTED => $query->whereNotNull('converted_invoice_id'),
            self::EXPIRED => $query
                ->whereNull('converted_invoice_id')
                ->whereDate('validity_until', '<', now('Asia/Riyadh')->toDateString()),
            default => null,
        };
    }

    public static function countOpen(): int
    {
        return (int) Quotation::query()
            ->whereNull('converted_invoice_id')
            ->whereDate('validity_until', '>=', now('Asia/Riyadh')->toDateString())
            ->count();
    }
}
