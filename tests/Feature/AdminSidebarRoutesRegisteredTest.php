<?php

use App\Support\Admin\AdminSidebarMenu;
use App\Support\Admin\DashboardQuickActionTiles;
use Illuminate\Support\Facades\Route;

/**
 * Every sidebar href must resolve to a registered named route (disabled nav when Route::has is false).
 *
 * @return list<string>
 */
function adminSidebarRouteNames(): array
{
    return [
        'admin.dashboard',
        'admin.invoices',
        DashboardQuickActionTiles::ROUTE_QUOTATIONS,
        'admin.catalog',
        DashboardQuickActionTiles::ROUTE_STOCK_SEARCH,
        'admin.inventory',
        'admin.customers',
        DashboardQuickActionTiles::ROUTE_PURCHASE_CREATE,
        AdminSidebarMenu::ROUTE_SUPPLIERS,
        AdminSidebarMenu::ROUTE_PAYMENTS_RECEIVED,
        DashboardQuickActionTiles::ROUTE_PAYMENTS_DUES,
        AdminSidebarMenu::ROUTE_EXPENSES,
        'admin.reports',
        'admin.users',
        'admin.team',
        'admin.profile',
        'admin.business',
    ];
}

it('has all admin sidebar target routes registered', function () {
    $missing = [];
    foreach (adminSidebarRouteNames() as $name) {
        if (! Route::has($name)) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBeEmpty('Unregistered admin sidebar routes: '.implode(', ', $missing));
});