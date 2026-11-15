<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Quotation;

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
        return array_merge(
            self::coreRoutes($branch, $posHomeStatus),
            self::extendedRoutes($branch),
        );
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: int, 3?: string|null}>
     */
    public static function coreRoutes(Branch $branch, int $posHomeStatus = 200): array
    {
        return [
            ['admin.dashboard', [], 200],
            ['admin.payments.dues', [], 200],
            ['admin.expenses', [], 200],
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
            ['admin.invoices.issue', ['mode' => 'standard'], 200],
            ['admin.quotations', [], 200],
            ['admin.quotations.create', [], 200],
            ['admin.catalog', [], 200],
            ['admin.inventory', [], 200],
            ['admin.stock.search', [], 200],
            ['admin.purchases.create', [], 200],
            ['admin.suppliers', [], 200],
            ['admin.team', [], 200],
        ];
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: int}>
     */
    public static function extendedRoutes(Branch $branch, ?Quotation $quotation = null, ?Invoice $invoice = null): array
    {
        $routes = [];

        if ($quotation !== null) {
            $routes[] = ['admin.quotations.edit', ['quotation' => $quotation], 200];
            $routes[] = ['admin.quotations.print', ['quotation' => $quotation], 200];
        }

        if ($invoice !== null) {
            $routes[] = ['admin.invoices.print', ['invoice' => $invoice], 200];
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    public static function livewireSmokeComponents(): array
    {
        return [
            \App\Livewire\Admin\AdminDashboard::class,
            \App\Livewire\Invoices\IssueInvoice::class,
            \App\Livewire\Invoices\InvoiceHub::class,
            \App\Livewire\Quotations\ManageQuotation::class,
            \App\Livewire\Quotations\QuotationHub::class,
            \App\Livewire\Admin\BusinessSettings::class,
            \App\Livewire\Catalog\MenuManager::class,
            \App\Livewire\Expenses\ExpensesHub::class,
            \App\Livewire\Purchases\SupplierHub::class,
        ];
    }
}