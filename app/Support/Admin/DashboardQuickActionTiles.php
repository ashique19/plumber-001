<?php

namespace App\Support\Admin;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Route;

final class DashboardQuickActionTiles
{
    /**
     * Canonical admin routes used by dashboard tiles (later PRs register these).
     */
    public const ROUTE_QUOTATIONS = 'admin.quotations';

    public const ROUTE_PURCHASE_CREATE = 'admin.purchases.create';

    public const ROUTE_PAYMENTS_DUES = 'admin.payments.dues';

    public const ROUTE_STOCK_SEARCH = 'admin.stock.search';

    /**
     * @return list<array{
     *     key: string,
     *     emoji: string,
     *     label: string,
     *     subtitle: string,
     *     href: string|null,
     *     enabled: bool,
     * }>
     */
    public static function forUser(?StaffUser $user, BusinessSetting $business): array
    {
        if ($user === null) {
            return [];
        }

        $defaultIssueMode = $business->defaultInvoiceDocumentType()->value;

        $definitions = [
            [
                'key' => 'create_invoice',
                'emoji' => '🧾',
                'label' => __('dashboard.tiles.create_invoice'),
                'subtitle' => __('dashboard.tiles.subtitles.create_invoice'),
                'visible' => $user->can('invoices.issue'),
                'route' => ['admin.invoices.issue', ['mode' => $defaultIssueMode]],
            ],
            [
                'key' => 'create_quotation',
                'emoji' => '📋',
                'label' => __('dashboard.tiles.create_quotation'),
                'subtitle' => __('dashboard.tiles.subtitles.create_quotation'),
                'visible' => $user->can('invoices.issue'),
                'route' => [self::ROUTE_QUOTATIONS, []],
            ],
            [
                'key' => 'add_product',
                'emoji' => '📦',
                'label' => __('dashboard.tiles.add_product'),
                'subtitle' => __('dashboard.tiles.subtitles.add_product'),
                'visible' => $user->can('inventory.view') && $user->can('staff.manage'),
                'route' => ['admin.catalog', ['tab' => 'products', 'create' => 'product']],
            ],
            [
                'key' => 'add_customer',
                'emoji' => '👥',
                'label' => __('dashboard.tiles.add_customer'),
                'subtitle' => __('dashboard.tiles.subtitles.add_customer'),
                'visible' => $user->can('reports.view'),
                'route' => ['admin.customers', ['create' => 'customer']],
            ],
            [
                'key' => 'check_inventory',
                'emoji' => '🏬',
                'label' => __('dashboard.tiles.check_inventory'),
                'subtitle' => __('dashboard.tiles.subtitles.check_inventory'),
                'visible' => $user->can('inventory.view'),
                'route' => self::resolveStockRoute(),
            ],
            [
                'key' => 'record_purchase',
                'emoji' => '🛒',
                'label' => __('dashboard.tiles.record_purchase'),
                'subtitle' => __('dashboard.tiles.subtitles.record_purchase'),
                'visible' => $user->can('inventory.manage'),
                'route' => [self::ROUTE_PURCHASE_CREATE, []],
            ],
            [
                'key' => 'payments_dues',
                'emoji' => '💳',
                'label' => __('dashboard.tiles.payments_dues'),
                'subtitle' => __('dashboard.tiles.subtitles.payments_dues'),
                'visible' => $user->can('reports.view'),
                'route' => [self::ROUTE_PAYMENTS_DUES, []],
            ],
            [
                'key' => 'view_reports',
                'emoji' => '📊',
                'label' => __('dashboard.tiles.view_reports'),
                'subtitle' => __('dashboard.tiles.subtitles.view_reports'),
                'visible' => $user->can('reports.view'),
                'route' => ['admin.reports', []],
            ],
        ];

        $tiles = [];
        foreach ($definitions as $definition) {
            if (! $definition['visible']) {
                continue;
            }

            [$routeName, $params] = $definition['route'];
            $enabled = Route::has($routeName);
            $href = $enabled ? route($routeName, $params) : null;

            $tiles[] = [
                'key' => $definition['key'],
                'emoji' => $definition['emoji'],
                'label' => $definition['label'],
                'subtitle' => $definition['subtitle'],
                'href' => $href,
                'enabled' => $enabled,
            ];
        }

        return $tiles;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function resolveStockRoute(): array
    {
        if (Route::has(self::ROUTE_STOCK_SEARCH)) {
            return [self::ROUTE_STOCK_SEARCH, []];
        }

        return ['admin.inventory', []];
    }
}
