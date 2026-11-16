<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Support\Hub\HubInvoiceZatcaBadge;

final class InvoicePrintClearance
{
    public static function isZatcaCleared(Invoice $invoice): bool
    {
        if ($invoice->document_type === InvoiceDocumentType::Simplified) {
            return true;
        }

        return HubInvoiceZatcaBadge::badgeKey($invoice) === HubInvoiceZatcaBadge::Cleared;
    }

    public static function allowsOfficialPrint(Invoice $invoice): bool
    {
        return self::hasValidGeneratedDocument($invoice);
    }

    public static function hasValidGeneratedDocument(Invoice $invoice): bool
    {
        if (! $invoice->isIssued()) {
            return false;
        }

        if ($invoice->document_type === InvoiceDocumentType::Simplified) {
            return true;
        }

        return self::isZatcaCleared($invoice);
    }
}