<?php

namespace App\Support\Admin;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Route;

final class DashboardQuickActionTiles
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     href: string|null,
     *     coming_soon: bool,
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
                'label' => __('dashboard.tiles.create_invoice'),
                'icon' => 'clipboard',
                'visible' => $user->can('invoices.issue'),
                'coming_soon' => false,
                'href' => Route::has('admin.invoices.issue')
                    ? route('admin.invoices.issue', ['mode' => $defaultIssueMode])
                    : null,
            ],
            [
                'key' => 'create_quotation',
                'label' => __('dashboard.tiles.create_quotation'),
                'icon' => 'document',
                'visible' => $user->can('invoices.issue'),
                'coming_soon' => true,
                'href' => null,
            ],
            [
                'key' => 'add_product',
                'label' => __('dashboard.tiles.add_product'),
                'icon' => 'plus',
                'visible' => $user->can('inventory.view') && $user->can('staff.manage'),
                'coming_soon' => false,
                'href' => route('admin.catalog', ['tab' => 'products', 'create' => 'product']),
            ],
            [
                'key' => 'add_customer',
                'label' => __('dashboard.tiles.add_customer'),
                'icon' => 'users',
                'visible' => $user->can('reports.view'),
                'coming_soon' => false,
                'href' => route('admin.customers', ['create' => 'customer']),
            ],
            [
                'key' => 'check_inventory',
                'label' => __('dashboard.tiles.check_inventory'),
                'icon' => 'cube',
                'visible' => $user->can('inventory.view'),
                'coming_soon' => false,
                'href' => route('admin.inventory'),
            ],
            [
                'key' => 'record_purchase',
                'label' => __('dashboard.tiles.record_purchase'),
                'icon' => 'cart',
                'visible' => $user->can('inventory.manage'),
                'coming_soon' => true,
                'href' => null,
            ],
            [
                'key' => 'payments_dues',
                'label' => __('dashboard.tiles.payments_dues'),
                'icon' => 'banknotes',
                'visible' => $user->can('reports.view'),
                'coming_soon' => true,
                'href' => null,
            ],
            [
                'key' => 'view_reports',
                'label' => __('dashboard.tiles.view_reports'),
                'icon' => 'chart',
                'visible' => $user->can('reports.view'),
                'coming_soon' => false,
                'href' => route('admin.reports'),
            ],
        ];

        $tiles = [];
        foreach ($definitions as $definition) {
            if (! $definition['visible']) {
                continue;
            }
            unset($definition['visible']);
            $tiles[] = $definition;
        }

        return $tiles;
    }
}