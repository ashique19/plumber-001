<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class HubInvoiceLifecycleTab
{
    public const ALL = 'all';

    public const DRAFTS = 'drafts';

    public const ISSUED = 'issued';

    public const AWAITING_CLEARANCE = 'awaiting_clearance';

    public const UNPAID = 'unpaid';

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [
            self::ALL,
            self::DRAFTS,
            self::ISSUED,
            self::AWAITING_CLEARANCE,
            self::UNPAID,
        ];
    }

    public static function normalize(string $tab): string
    {
        return in_array($tab, self::allowed(), true) ? $tab : self::ALL;
    }

    public static function applyScope(Builder $query, string $tab): void
    {
        $tab = self::normalize($tab);

        match ($tab) {
            self::DRAFTS => $query->where('status', InvoiceStatus::Draft),
            self::ISSUED => $query->where('status', InvoiceStatus::Issued),
            self::AWAITING_CLEARANCE => self::applyAwaitingClearanceScope($query),
            self::UNPAID => self::applyUnpaidScope($query),
            default => null,
        };
    }

    public static function countDrafts(): int
    {
        return (int) Invoice::query()->where('status', InvoiceStatus::Draft)->count();
    }

    private static function applyAwaitingClearanceScope(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Issued)
            ->where('document_type', InvoiceDocumentType::Standard);

        $ids = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->where('document_type', InvoiceDocumentType::Standard)
            ->get()
            ->filter(fn (Invoice $invoice): bool => HubInvoiceRowActions::showAwaitingClearanceControls($invoice))
            ->pluck('id');

        $query->whereIn('id', $ids->isEmpty() ? [-1] : $ids);
    }

    private static function applyUnpaidScope(Builder $query): void
    {
        $ledger = app(InvoiceDuesLedgerService::class);

        /** @var Collection<int, Invoice> $issued */
        $issued = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->with('payments')
            ->get();

        $ids = $issued
            ->filter(fn (Invoice $invoice): bool => $ledger->balanceFor($invoice)['principal_outstanding'] > 0.005)
            ->pluck('id');

        $query->whereIn('id', $ids->isEmpty() ? [-1] : $ids);
    }
}
