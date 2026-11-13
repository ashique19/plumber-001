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
    public static function adminRoutes(Branch $branch): array
    {
        return [
            ['admin.dashboard', [], 200],
            ['admin.business', [], 200],
            ['admin.pos.home', [], 200],
            ['admin.pos.terminal', ['branch' => $branch], 200],
            ['admin.pos.history', ['branch' => $branch], 200],
            ['admin.pos.eod', ['branch' => $branch], 200],
            ['admin.pos.ops', ['branch' => $branch], 200],
            ['admin.reports', [], 200],
            ['admin.customers', [], 200],
            ['admin.invoices', [], 200],
            ['admin.invoices.issue', ['mode' => 'simplified'], 200],
            ['admin.catalog', [], 200],
            ['admin.inventory', [], 200],
            ['admin.team', [], 200],
        ];
    }
}
