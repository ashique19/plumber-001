<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Invoicing\InvoicePrintClearance;
use App\Support\Invoicing\ZatcaSubmissionStatus;

/**
 * Single source of truth for invoice hub row actions (UI + server guards).
 */
final class HubInvoiceRowActions
{
    public static function canView(Invoice $invoice): bool
    {
        return true;
    }

    public static function canEdit(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft;
    }

    public static function canDownloadOrPrint(Invoice $invoice): bool
    {
        return InvoicePrintClearance::hasValidGeneratedDocument($invoice);
    }

    /**
     * Issued standard invoice not yet ZATCA-cleared: show disabled print/download with tooltip only.
     */
    public static function showAwaitingClearanceControls(Invoice $invoice): bool
    {
        if (! $invoice->isIssued()) {
            return false;
        }

        if ($invoice->document_type === InvoiceDocumentType::Simplified) {
            return false;
        }

        return ! InvoicePrintClearance::isZatcaCleared($invoice);
    }

    public static function canRecordPayment(Invoice $invoice, float $dueAmount): bool
    {
        return $invoice->isIssued() && $dueAmount > 0.005;
    }

    public static function canDeleteDraft(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft;
    }

    public static function canResendZatca(Invoice $invoice): bool
    {
        if (! $invoice->isIssued() || ! ZatcaSubmissionStatus::canSubmitToZatca()) {
            return false;
        }

        if (HubInvoiceZatcaTab::isSuccess($invoice)) {
            return false;
        }

        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);

        if (in_array($badge, [
            HubInvoiceZatcaBadge::Rejected,
            HubInvoiceZatcaBadge::NotSubmitted,
        ], true)) {
            return true;
        }

        return HubInvoiceZatcaTab::isFailed($invoice)
            && $badge !== HubInvoiceZatcaBadge::Pending;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forInvoice(Invoice $invoice, float $dueAmount): array
    {
        $canPrint = self::canDownloadOrPrint($invoice);
        $awaiting = self::showAwaitingClearanceControls($invoice);

        $mode = $invoice->document_type->value === 'standard' ? 'standard' : 'simplified';

        return [
            'view' => self::canView($invoice),
            'edit' => self::canEdit($invoice),
            'edit_url' => self::canEdit($invoice)
                ? route('admin.invoices.issue', ['mode' => $mode, 'draft' => $invoice->id])
                : null,
            'download' => $canPrint,
            'print' => $canPrint,
            'awaiting_clearance' => $awaiting && ! $canPrint,
            'record_payment' => self::canRecordPayment($invoice, $dueAmount),
            'delete_draft' => self::canDeleteDraft($invoice),
            'resend_zatca' => self::canResendZatca($invoice),
        ];
    }
}