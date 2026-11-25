<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;

final class HubInvoiceZatcaBadge
{
    public const NotSent = 'not_sent';

    public const ReportingPending = 'reporting_pending';

    public const ClearancePending = 'clearance_pending';

    /** @deprecated Use ClearancePending or ReportingPending */
    public const Pending = self::ClearancePending;

    public const Reported = 'reported';

    public const ReportedWithWarnings = 'reported_warnings';

    public const Cleared = 'cleared';

    public const Rejected = 'rejected';

    public const AutoRetry = 'auto_retry';

  /**
   * @return list<string>
   */
    public static function pendingKeys(): array
    {
        return [
            self::ReportingPending,
            self::ClearancePending,
            self::AutoRetry,
            self::NotSent,
        ];
    }

    public static function isPendingKey(string $badge): bool
    {
        return in_array($badge, self::pendingKeys(), true);
    }

    public static function badgeKey(Invoice $invoice): string
    {
        $outcome = \App\Support\Invoicing\ZHeartSubmissionOutcome::normalize(data_get($invoice->payload, 'zheart_outcome'));
        if ($outcome === \App\Support\Invoicing\ZHeartSubmissionOutcome::RETRY) {
            return self::AutoRetry;
        }
        if ($outcome === \App\Support\Invoicing\ZHeartSubmissionOutcome::REJECTED) {
            return self::Rejected;
        }
        if ($outcome === \App\Support\Invoicing\ZHeartSubmissionOutcome::ACCEPTED
            && data_get($invoice->payload, 'zheart_remote_submitted') === true) {
            $isSimplified = in_array($invoice->document_type, [InvoiceDocumentType::Simplified], true)
                || data_get($invoice->payload, 'invoice_category') === 'simplified';
            $raw = strtolower((string) (
                data_get($invoice->payload, 'zheart_zatca.status')
                ?? data_get($invoice->payload, 'zheart_reporting_status', '')
            ));
            if (in_array($raw, ['warning', 'warnings'], true)) {
                return self::ReportedWithWarnings;
            }
            if (! $isSimplified && in_array($raw, ['cleared', 'clear', 'accepted'], true)) {
                return self::Cleared;
            }

            return $isSimplified ? self::Reported : (in_array($raw, ['reported'], true) ? self::Reported : self::Cleared);
        }

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
            return self::NotSent;
        }

        $isSimplified = in_array($invoice->document_type, [InvoiceDocumentType::Simplified], true)
            || data_get($invoice->payload, 'invoice_category') === 'simplified';

        $remote = data_get($invoice->payload, 'zheart_remote_submitted') === true;

        if ($isSimplified) {
            if ($remote && in_array($raw, ['warning', 'warnings'], true)) {
                return self::ReportedWithWarnings;
            }

            if ($remote && in_array($raw, ['reported', 'cleared', 'clear', 'accepted', ''], true)) {
                return self::Reported;
            }

            if ($submission === 'signed_locally' || $raw === 'pending' || ($remote && $raw === '')) {
                return self::ReportingPending;
            }

            return self::NotSent;
        }

        if (in_array($raw, ['warning', 'warnings'], true) && $remote) {
            return self::ReportedWithWarnings;
        }

        if (in_array($raw, ['cleared', 'clear', 'accepted'], true) && $remote) {
            return self::Cleared;
        }

        if ($raw === 'reported' && $remote) {
            return self::Reported;
        }

        if ($raw === 'pending' || $submission === 'signed_locally' || ! filled($invoice->signed_xml)) {
            return self::ClearancePending;
        }

        if ($submission === 'not_submitted' || ! $remote) {
            return self::NotSent;
        }

        return self::ClearancePending;
    }

    public static function labelKey(Invoice $invoice): string
    {
        return 'invoices.zatca_badge.'.self::badgeKey($invoice);
    }
}