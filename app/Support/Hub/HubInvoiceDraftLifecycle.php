<?php

namespace App\Support\Hub;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\StaffUser;

/**
 * Draft-only invoice actions for hub and print views (UI + server guards).
 */
final class HubInvoiceDraftLifecycle
{
    public static function canEdit(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft;
    }

    public static function wasEverSentOrSignedForZatca(Invoice $invoice): bool
    {
        if ($invoice->isIssued()) {
            return true;
        }

        if (data_get($invoice->payload, 'zheart_remote_submitted') === true) {
            return true;
        }

        if (filled($invoice->signed_xml)) {
            return true;
        }

        $submission = (string) data_get($invoice->payload, 'signing_submission_status', '');

        return in_array($submission, ['submitted', 'signed_locally'], true);
    }

    public static function canDelete(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft
            && ! self::wasEverSentOrSignedForZatca($invoice);
    }

    public static function canIssue(Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return false;
        }

        $lines = $invoice->payload['lines'] ?? [];

        return is_array($lines) && $lines !== [];
    }

    public static function editUrl(Invoice $invoice): ?string
    {
        if (! self::canEdit($invoice)) {
            return null;
        }

        $mode = $invoice->document_type->value === 'standard' ? 'standard' : 'simplified';

        return route('admin.invoices.issue', ['mode' => $mode, 'draft' => $invoice->id]);
    }

    public static function canPreview(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft;
    }

    public static function previewUrl(Invoice $invoice): ?string
    {
        if (! self::canPreview($invoice)) {
            return null;
        }

        $layout = HubInvoicePrintLinks::layout($invoice);

        return route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => $layout,
            'preview' => 1,
        ]);
    }

    public static function canAuthorize(?StaffUser $user, Invoice $invoice): bool
    {
        if ($user === null || ! self::canIssue($invoice)) {
            return false;
        }

        return $user->hasAnyRole(['owner', 'manager']);
    }

    public static function recordAuthorization(Invoice $invoice, StaffUser $user): void
    {
        $payload = $invoice->payload ?? [];
        $payload['draft_authorized_at'] = now('UTC')->toIso8601String();
        $payload['draft_authorized_by'] = $user->id;

        $invoice->forceFill(['payload' => $payload])->save();
    }
}