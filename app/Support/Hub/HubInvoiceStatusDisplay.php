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
        return self::awaitingStandardClearance($invoice);
    }

    public static function invoiceStatusLabel(Invoice $invoice): string
    {
        $key = HubInvoiceTableRow::invoiceStatusKey($invoice);
        if (self::awaitingStandardClearance($invoice) && $key === 'pending') {
            return (string) __('invoices.awaiting_zatca_clearance_short');
        }

        return (string) __('invoices.invoice_status.'.$key);
    }

    public static function zatcaBadgeLabel(Invoice $invoice): string
    {
        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);
        if (self::awaitingStandardClearance($invoice) && $badge === HubInvoiceZatcaBadge::Pending) {
            return (string) __('invoices.awaiting_zatca_clearance_short');
        }

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
        $key = HubInvoiceTableRow::invoiceStatusKey($invoice);

        return match ($key) {
            'pending' => 'warning',
            'draft' => 'refunded',
            default => 'paid',
        };
    }

    public static function zatcaBadgePillVariant(Invoice $invoice): string
    {
        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);

        return match ($badge) {
            HubInvoiceZatcaBadge::Cleared, HubInvoiceZatcaBadge::Reported => 'paid',
            HubInvoiceZatcaBadge::Pending => 'warning',
            default => 'refunded',
        };
    }
}