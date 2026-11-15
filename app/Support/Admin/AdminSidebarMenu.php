<?php

namespace App\Support\Admin;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\BusinessSettingDisplayName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class AdminSidebarMenu
{
    public const ROUTE_SUPPLIERS = 'admin.suppliers';

    public const ROUTE_PAYMENTS_RECEIVED = 'admin.payments.received';

    public const ROUTE_EXPENSES = 'admin.expenses';

    /**
     * @return array{
     *     businessName: string,
     *     activeId: string|null,
     *     groups: list<array{
     *         key: string,
     *         label: string,
     *         type: 'group'|'link',
     *         expanded: bool,
     *         items: list<array{
     *             id: string,
     *             label: string,
     *             href: string|null,
     *             enabled: bool,
     *             active: bool,
     *         }>
     *     }>
     * }
     */
    public static function build(?StaffUser $user, BusinessSetting $business, ?Request $request = null): array
    {
        $request ??= request();
        $activeId = self::resolveActiveItemId($request);

        $raw = self::definitions($user, $business);
        $groups = [];

        foreach ($raw as $entry) {
            if (! self::userCanSeeEntry($user, $entry)) {
                continue;
            }

            if ($entry['type'] === 'link') {
                $item = self::hydrateItem($entry, $activeId);
                $groups[] = [
                    'key' => $entry['id'],
                    'label' => $item['label'],
                    'type' => 'link',
                    'expanded' => true,
                    'items' => [$item],
                ];

                continue;
            }

            $items = [];
            foreach ($entry['children'] as $child) {
                if (! self::userCanSeeEntry($user, $child)) {
                    continue;
                }
                $items[] = self::hydrateItem($child, $activeId);
            }

            if ($items === []) {
                continue;
            }

            $expanded = collect($items)->contains(fn (array $item) => $item['active']);

            $groups[] = [
                'key' => $entry['id'],
                'label' => __('admin_nav.groups.'.$entry['id']),
                'type' => 'group',
                'expanded' => $expanded,
                'items' => $items,
            ];
        }

        $name = BusinessSettingDisplayName::for($business, app()->getLocale());
        if (trim($name) === '') {
            $name = __('admin_nav.business_fallback');
        }

        return [
            'businessName' => $name,
            'activeId' => $activeId,
            'groups' => $groups,
        ];
    }

    public static function resolveActiveItemId(Request $request): ?string
    {
        $name = Route::currentRouteName() ?? '';
        $tab = (string) $request->query('tab', '');
        $typeFilter = (string) $request->query('typeFilter', '');
        $section = (string) $request->query('section', '');

        return match (true) {
            $name === 'admin.dashboard' => 'dashboard',
            $name === 'admin.quotations', str_starts_with($name, 'admin.quotations.') => 'quotations',
            $name === 'admin.invoices' && $typeFilter === 'credit_note' => 'sales_returns',
            str_starts_with($name, 'admin.invoices') => 'invoices',
            $name === 'admin.catalog' && $tab === 'categories' => 'categories',
            $name === 'admin.catalog' => 'all_products',
            $name === 'admin.stock.search', str_starts_with($name, 'admin.stock.') => 'stock_management',
            $name === 'admin.inventory' => 'stock_management',
            $name === 'admin.customers', str_starts_with($name, 'admin.customers.') => 'customers',
            $name === 'admin.purchases.create', str_starts_with($name, 'admin.purchases.') => 'purchase_records',
            $name === 'admin.suppliers', str_starts_with($name, 'admin.suppliers.') => 'suppliers',
            $name === 'admin.payments.received', str_starts_with($name, 'admin.payments.received') => 'received_payments',
            $name === 'admin.payments.dues', str_starts_with($name, 'admin.payments.dues') => 'outstanding_payments',
            $name === 'admin.expenses', str_starts_with($name, 'admin.expenses.') => 'expenses',
            $name === 'admin.reports' && $section === 'profit' => 'profit_reports',
            $name === 'admin.reports' && $section === 'inventory' => 'inventory_reports',
            $name === 'admin.reports' && $section === 'customers' => 'customer_reports',
            $name === 'admin.reports' => 'sales_reports',
            $name === 'admin.team', str_starts_with($name, 'admin.team') => 'staff_management',
            $name === 'admin.business' => 'settings',
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function definitions(?StaffUser $user, BusinessSetting $business): array
    {
        return [
            [
                'id' => 'dashboard',
                'type' => 'link',
                'label_key' => 'dashboard',
                'permissions' => ['reports.view'],
                'route' => ['admin.dashboard', []],
            ],
            [
                'id' => 'sales',
                'type' => 'group',
                'permissions' => [],
                'children' => [
                    [
                        'id' => 'invoices',
                        'label_key' => 'invoices',
                        'permissions' => ['invoices.issue', 'reports.view'],
                        'permission_mode' => 'any',
                        'route' => ['admin.invoices', []],
                    ],
                    [
                        'id' => 'quotations',
                        'label_key' => 'quotations',
                        'permissions' => ['invoices.issue'],
                        'route' => [DashboardQuickActionTiles::ROUTE_QUOTATIONS, []],
                    ],
                    [
                        'id' => 'sales_returns',
                        'label_key' => 'sales_returns',
                        'permissions' => ['invoices.issue', 'reports.view'],
                        'permission_mode' => 'any',
                        'route' => ['admin.invoices', ['typeFilter' => 'credit_note']],
                    ],
                ],
            ],
            [
                'id' => 'products',
                'type' => 'group',
                'permissions' => ['inventory.view'],
                'children' => [
                    [
                        'id' => 'all_products',
                        'label_key' => 'all_products',
                        'permissions' => ['inventory.view'],
                        'route' => ['admin.catalog', ['tab' => 'products']],
                    ],
                    [
                        'id' => 'categories',
                        'label_key' => 'categories',
                        'permissions' => ['inventory.view', 'staff.manage'],
                        'permission_mode' => 'all',
                        'route' => ['admin.catalog', ['tab' => 'categories']],
                    ],
                    [
                        'id' => 'stock_management',
                        'label_key' => 'stock_management',
                        'permissions' => ['inventory.view'],
                        'route' => self::stockRoute(),
                    ],
                ],
            ],
            [
                'id' => 'customers',
                'type' => 'link',
                'label_key' => 'customers',
                'permissions' => ['reports.view'],
                'route' => ['admin.customers', []],
            ],
            [
                'id' => 'purchases',
                'type' => 'group',
                'permissions' => ['inventory.manage'],
                'children' => [
                    [
                        'id' => 'purchase_records',
                        'label_key' => 'purchase_records',
                        'permissions' => ['inventory.manage'],
                        'route' => [DashboardQuickActionTiles::ROUTE_PURCHASE_CREATE, []],
                    ],
                    [
                        'id' => 'suppliers',
                        'label_key' => 'suppliers',
                        'permissions' => ['inventory.manage'],
                        'route' => [self::ROUTE_SUPPLIERS, []],
                    ],
                ],
            ],
            [
                'id' => 'payments',
                'type' => 'group',
                'permissions' => ['reports.view'],
                'children' => [
                    [
                        'id' => 'received_payments',
                        'label_key' => 'received_payments',
                        'permissions' => ['reports.view'],
                        'route' => [self::ROUTE_PAYMENTS_RECEIVED, []],
                    ],
                    [
                        'id' => 'outstanding_payments',
                        'label_key' => 'outstanding_payments',
                        'permissions' => ['reports.view'],
                        'route' => [DashboardQuickActionTiles::ROUTE_PAYMENTS_DUES, []],
                    ],
                ],
            ],
            [
                'id' => 'expenses',
                'type' => 'link',
                'label_key' => 'expenses',
                'permissions' => ['reports.view'],
                'route' => [self::ROUTE_EXPENSES, []],
            ],
            [
                'id' => 'reports',
                'type' => 'group',
                'permissions' => ['reports.view'],
                'children' => [
                    [
                        'id' => 'sales_reports',
                        'label_key' => 'sales_reports',
                        'permissions' => ['reports.view'],
                        'route' => ['admin.reports', []],
                    ],
                    [
                        'id' => 'profit_reports',
                        'label_key' => 'profit_reports',
                        'permissions' => ['reports.view'],
                        'route' => ['admin.reports', ['section' => 'profit']],
                    ],
                    [
                        'id' => 'inventory_reports',
                        'label_key' => 'inventory_reports',
                        'permissions' => ['reports.view'],
                        'route' => ['admin.reports', ['section' => 'inventory']],
                    ],
                    [
                        'id' => 'customer_reports',
                        'label_key' => 'customer_reports',
                        'permissions' => ['reports.view'],
                        'route' => ['admin.reports', ['section' => 'customers']],
                    ],
                ],
            ],
            [
                'id' => 'staff_management',
                'type' => 'link',
                'label_key' => 'staff_management',
                'permissions' => ['staff.manage'],
                'route' => ['admin.team', []],
            ],
            [
                'id' => 'settings',
                'type' => 'link',
                'label_key' => 'settings',
                'permissions' => ['business.manage'],
                'route' => ['admin.business', []],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function userCanSeeEntry(?StaffUser $user, array $entry): bool
    {
        if ($user === null) {
            return false;
        }

        $permissions = $entry['permissions'] ?? [];
        if ($permissions === []) {
            return true;
        }

        $mode = $entry['permission_mode'] ?? 'all';

        if ($mode === 'any') {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{id: string, label: string, href: string|null, enabled: bool, active: bool}
     */
    private static function hydrateItem(array $entry, ?string $activeId): array
    {
        [$routeName, $params] = $entry['route'];
        $enabled = Route::has($routeName);
        $href = $enabled ? route($routeName, $params) : null;
        $id = (string) $entry['id'];

        return [
            'id' => $id,
            'label' => __('admin_nav.items.'.$entry['label_key']),
            'href' => $href,
            'enabled' => $enabled,
            'active' => $activeId === $id,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function stockRoute(): array
    {
        if (Route::has(DashboardQuickActionTiles::ROUTE_STOCK_SEARCH)) {
            return [DashboardQuickActionTiles::ROUTE_STOCK_SEARCH, []];
        }

        return ['admin.inventory', []];
    }
}