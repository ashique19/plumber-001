<?php

namespace App\Support\Hub;

use App\Models\Invoice;
use App\Support\Invoicing\ZatcaSubmissionStatus;

final class HubDocumentSigning
{
    public static function signingKey(Invoice $invoice): string
    {
        if (ZatcaSubmissionStatus::invoiceNotSentToZatca($invoice)) {
            return 'zatca_not_sent';
        }

        if (! filled($invoice->signed_xml)) {
            return 'none';
        }

        if (filled(data_get($invoice->payload, 'zheart_zatca'))
            || data_get($invoice->payload, 'zheart_remote_submitted')) {
            return 'submitted';
        }

        return 'signed';
    }

    public static function signingFilterMatches(Invoice $invoice, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        return self::signingKey($invoice) === $filter;
    }
}