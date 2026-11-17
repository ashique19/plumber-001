<?php

namespace App\Support\Reports;

final class OwnerAnalyticsSection
{
    public const SALES = 'sales';

    public const PROFIT = 'profit';

    public const INVENTORY = 'inventory';

    public const CUSTOMERS = 'customers';

    public static function normalize(string $section): string
    {
        $section = strtolower(trim($section));

        return match ($section) {
            self::PROFIT, self::INVENTORY, self::CUSTOMERS => $section,
            default => self::SALES,
        };
    }

    public static function showsSummaryKpis(string $section): bool
    {
        return $section === self::SALES;
    }

    public static function showsCharts(string $section): bool
    {
        return $section === self::SALES;
    }

    public static function showsVatSummary(string $section): bool
    {
        return $section === self::PROFIT;
    }

    public static function showsProfit(string $section): bool
    {
        return $section === self::PROFIT;
    }

    public static function showsInventory(string $section): bool
    {
        return $section === self::INVENTORY;
    }

    public static function showsDues(string $section): bool
    {
        return $section === self::CUSTOMERS;
    }

    public static function titleKey(string $section): string
    {
        return match ($section) {
            self::PROFIT => 'reports.title_profit',
            self::INVENTORY => 'reports.title_inventory',
            self::CUSTOMERS => 'reports.title_customers',
            default => 'reports.title',
        };
    }

    public static function subtitleKey(string $section): string
    {
        return match ($section) {
            self::PROFIT => 'reports.subtitle_profit',
            self::INVENTORY => 'reports.subtitle_inventory',
            self::CUSTOMERS => 'reports.subtitle_customers',
            default => 'reports.subtitle',
        };
    }
}