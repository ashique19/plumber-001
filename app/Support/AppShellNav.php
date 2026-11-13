<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Route;

final class AppShellNav
{
    public static function activeTab(): string
    {
        $name = Route::currentRouteName() ?? '';

        return match (true) {
            $name === 'admin.dashboard' => 'dashboard',
            str_contains($name, 'admin.pos.terminal') => 'pos',
            str_contains($name, 'admin.pos.home') => 'pos',
            str_contains($name, 'admin.pos.history') => 'orders',
            str_contains($name, 'admin.pos.receipt') => 'orders',
            str_contains($name, 'admin.pos.eod') => 'eod',
            str_contains($name, 'admin.pos.ops') => 'ops',
            str_contains($name, 'admin.reports') => 'analytics',
            str_contains($name, 'admin.customers') => 'customers',
            str_contains($name, 'admin.invoices.issue') => 'invoices-issue',
            str_contains($name, 'admin.invoices') => 'invoices',
            str_contains($name, 'admin.catalog') => 'catalog',
            str_contains($name, 'admin.inventory') => 'inventory',
            str_contains($name, 'admin.team') => 'team',
            str_contains($name, 'admin.business') => 'business',
            default => 'pos',
        };
    }

    /**
     * @return list<Branch>
     */
    public static function branchesForSwitcher(BusinessSetting $tenant): array
    {
        return Branch::query()
            ->withoutGlobalScopes()
            ->orderBy('name')
            ->get()
            ->all();
    }
}
