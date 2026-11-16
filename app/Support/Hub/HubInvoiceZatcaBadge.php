<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;

final class HubInvoiceZatcaBadge
{
    public const NotSubmitted = 'not_submitted';

    public const Pending = 'pending';

    public const Cleared = 'cleared';

    public const Reported = 'reported';

    public const Rejected = 'rejected';

    public static function badgeKey(Invoice $invoice): string
    {
        if (filled(data_get($invoice->payload, 'zheart_last_error'))) {
            return self::Rejected;
        }

        $raw = strtolower((string) (
            data_get($invoice->payload, 'zheart_zatca.status')
            ?? data_get($invoice->payload, 'zheart_reporting_status', '')
        ));

        if (in_array($raw, ['rejected', 'failed', 'error', 'pending_error'], true)) {
            return self::Rejected;
        }

        $submission = (string) data_get($invoice->payload, 'signing_submission_status', '');

        if ($submission === 'not_submitted' && ! filled($invoice->signed_xml)) {
            return self::NotSubmitted;
        }

        $isSimplified = in_array($invoice->document_type, [InvoiceDocumentType::Simplified], true)
            || data_get($invoice->payload, 'invoice_category') === 'simplified';

        $remote = data_get($invoice->payload, 'zheart_remote_submitted') === true;

        if ($isSimplified) {
            if ($remote && in_array($raw, ['reported', 'cleared', 'clear', 'accepted', 'warning', 'warnings', ''], true)) {
                return self::Reported;
            }

            if ($submission === 'signed_locally' || $raw === 'pending' || ($remote && $raw === '')) {
                return self::Pending;
            }

            return self::NotSubmitted;
        }

        if (in_array($raw, ['cleared', 'clear', 'accepted', 'reported'], true) && $remote) {
            return self::Cleared;
        }

        if ($raw === 'pending' || $submission === 'signed_locally' || ! filled($invoice->signed_xml)) {
            return self::Pending;
        }

        if ($submission === 'not_submitted' || ! $remote) {
            return self::NotSubmitted;
        }

        return self::Pending;
    }

    public static function labelKey(Invoice $invoice): string
    {
        return 'invoices.zatca_badge.'.self::badgeKey($invoice);
    }
}