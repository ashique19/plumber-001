<?php

namespace App\Support\Hub;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

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
}