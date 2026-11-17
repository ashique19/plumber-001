<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\InvoicePrintClearance;

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
        return HubInvoiceDraftLifecycle::canEdit($invoice);
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
        return HubInvoiceDraftLifecycle::canDelete($invoice);
    }

    public static function canIssueDraft(Invoice $invoice): bool
    {
        return HubInvoiceDraftLifecycle::canIssue($invoice);
    }

    public static function canResendZatca(Invoice $invoice): bool
    {
        return HubInvoiceZatcaTab::canResend($invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forInvoice(Invoice $invoice, float $dueAmount, ?StaffUser $actor = null): array
    {
        $canPrint = self::canDownloadOrPrint($invoice);
        $awaiting = self::showAwaitingClearanceControls($invoice);
        $actor ??= auth('staff')->user();

        $canIssueFromHub = self::canIssueDraft($invoice)
            && $actor instanceof StaffUser
            && $actor->hasAnyRole(['owner', 'manager']);

        return [
            'view' => self::canView($invoice),
            'edit' => self::canEdit($invoice),
            'edit_url' => HubInvoiceDraftLifecycle::editUrl($invoice),
            'issue_draft' => $canIssueFromHub,
            'preview_draft' => HubInvoiceDraftLifecycle::canPreview($invoice),
            'preview_draft_url' => HubInvoiceDraftLifecycle::previewUrl($invoice),
            'authorize_draft' => HubInvoiceDraftLifecycle::canAuthorize($actor, $invoice),
            'download' => $canPrint,
            'print' => $canPrint,
            'awaiting_clearance' => $awaiting && ! $canPrint,
            'record_payment' => self::canRecordPayment($invoice, $dueAmount),
            'delete_draft' => self::canDeleteDraft($invoice),
            'resend_zatca' => self::canResendZatca($invoice),
            'resend_zatca_label' => self::canResendZatca($invoice)
                ? HubInvoiceZatcaResubmitCopy::actionLabel($invoice)
                : null,
        ];
    }
}
