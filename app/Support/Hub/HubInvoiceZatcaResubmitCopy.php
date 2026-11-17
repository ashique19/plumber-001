<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;

/**
 * User-facing copy for ZATCA retry actions (hub row, invoice view, bulk).
 */
final class HubInvoiceZatcaResubmitCopy
{
    public static function usesReportPath(Invoice $invoice): bool
    {
        return $invoice->document_type === InvoiceDocumentType::Simplified;
    }

    public static function actionLabel(Invoice $invoice): string
    {
        return self::usesReportPath($invoice)
            ? (string) __('invoices.action_send_report_zatca')
            : (string) __('invoices.action_send_for_clearance');
    }
}