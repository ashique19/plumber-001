<?php

namespace App\Support\Hub;

use App\Models\Invoice;

final class HubInvoiceStatusDisplay
{
    public static function awaitingStandardClearance(Invoice $invoice): bool
    {
        return HubInvoiceRowActions::showAwaitingClearanceControls($invoice);
    }

    public static function showsClearanceHint(Invoice $invoice): bool
    {
        if (! self::awaitingStandardClearance($invoice)) {
            return false;
        }

        return HubInvoiceZatcaBadge::badgeKey($invoice) === HubInvoiceZatcaBadge::ClearancePending;
    }

    public static function invoiceStatusLabel(Invoice $invoice): string
    {
        $key = HubInvoiceTableRow::invoiceStatusKey($invoice);

        return (string) __('invoices.invoice_status.'.$key);
    }

    public static function zatcaBadgeLabel(Invoice $invoice): string
    {
        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);

        return (string) __('invoices.zatca_badge.'.$badge);
    }

    public static function clearanceHint(): string
    {
        return (string) __('invoices.zatca_clearance_hint');
    }

    public static function awaitingClearanceTooltip(): string
    {
        return (string) __('invoices.awaiting_zatca_clearance');
    }

    public static function invoiceStatusPillVariant(Invoice $invoice): string
    {
        return match (HubInvoiceTableRow::invoiceStatusKey($invoice)) {
            'draft' => 'muted',
            'cancelled' => 'cancelled',
            'credited', 'returned' => 'refunded',
            default => 'info',
        };
    }

    public static function zatcaBadgePillVariant(Invoice $invoice): string
    {
        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);

        return match ($badge) {
            HubInvoiceZatcaBadge::Cleared,
            HubInvoiceZatcaBadge::Reported => 'paid',
            HubInvoiceZatcaBadge::ReportedWithWarnings => 'warning',
            HubInvoiceZatcaBadge::Rejected => 'cancelled',
            default => 'warning',
        };
    }
}