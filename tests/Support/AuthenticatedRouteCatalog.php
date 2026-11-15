<?php

namespace Tests\Support;

use App\Models\Branch;

/**
 * Authenticated GET routes used for post-login smoke tests (single-client).
 */
final class AuthenticatedRouteCatalog
{
    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: int, 3?: string|null}>
     */
    public static function adminRoutes(Branch $branch, int $posHomeStatus = 200): array
    {
        return [
            ['admin.dashboard', [], 200],
            ['admin.payments.dues', [], 200],
            ['admin.business', [], 200],
            ['admin.pos.home', [], $posHomeStatus],
            ['admin.pos.terminal', ['branch' => $branch], 200],
            ['admin.pos.history', ['branch' => $branch], 200],
            ['admin.pos.eod', ['branch' => $branch], 200],
            ['admin.pos.ops', ['branch' => $branch], 200],
            ['admin.reports', [], 200],
            ['admin.customers', [], 200],
            ['admin.invoices', [], 200],
            ['admin.invoices.issue', ['mode' => 'simplified'], 200],
            ['admin.quotations', [], 200],
            ['admin.quotations.create', [], 200],
            ['admin.catalog', [], 200],
            ['admin.inventory', [], 200],
            ['admin.stock.search', [], 200],
            ['admin.purchases.create', [], 200],
            ['admin.team', [], 200],
        ];
    }
}
