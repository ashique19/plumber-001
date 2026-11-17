<?php

namespace App\Support\Invoicing;

use App\Models\Invoice;
use App\Support\Hub\HubInvoiceZatcaTab;

final class InvoiceZatcaSubmissionGuard
{
    public const LOCK_SECONDS = 180;

    public const PAYLOAD_IN_FLIGHT = 'zheart_submission_in_flight';

    public static function lockKey(int $invoiceId): string
    {
        return 'invoice-zatca-submission:'.$invoiceId;
    }

    public static function alreadyRemoteSubmitted(Invoice $invoice): bool
    {
        return data_get($invoice->payload, 'zheart_remote_submitted') === true;
    }

    public static function submissionInFlight(Invoice $invoice): bool
    {
        return data_get($invoice->payload, self::PAYLOAD_IN_FLIGHT) === true;
    }

    public static function effectiveReportingStatus(Invoice $invoice): string
    {
        $zatca = strtolower((string) data_get($invoice->payload, 'zheart_zatca.status', ''));
        $reporting = strtolower((string) data_get($invoice->payload, 'zheart_reporting_status', ''));

        return $zatca !== '' ? $zatca : $reporting;
    }

    public static function remoteSubmissionSucceeded(Invoice $invoice): bool
    {
        if (! self::alreadyRemoteSubmitted($invoice)) {
            return false;
        }

        if (HubInvoiceZatcaTab::isSuccess($invoice)) {
            return true;
        }

        $effective = self::effectiveReportingStatus($invoice);

        return in_array($effective, HubInvoiceZatcaTab::successReportingStatuses(), true);
    }

    public static function shouldSkipRemoteSubmission(Invoice $invoice): bool
    {
        if (self::submissionInFlight($invoice)) {
            return true;
        }

        return self::alreadyRemoteSubmitted($invoice) && self::remoteSubmissionSucceeded($invoice);
    }

    public static function shouldSkipResubmit(Invoice $invoice): bool
    {
        return self::shouldSkipRemoteSubmission($invoice);
    }

    public static function markSubmissionInFlight(Invoice $invoice): void
    {
        $payload = array_merge($invoice->payload ?? [], [
            self::PAYLOAD_IN_FLIGHT => true,
        ]);

        Invoice::withoutEvents(function () use ($invoice, $payload): void {
            $invoice->forceFill(['payload' => $payload])->save();
        });
    }

    public static function clearSubmissionInFlight(Invoice $invoice): void
    {
        $payload = $invoice->payload ?? [];
        unset($payload[self::PAYLOAD_IN_FLIGHT]);

        Invoice::withoutEvents(function () use ($invoice, $payload): void {
            $invoice->forceFill(['payload' => $payload])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $signed
     */
    public static function standardClearanceErrorMessage(array $signed, string $invoiceCategory): ?string
    {
        if ($invoiceCategory !== 'standard' || ! ($signed['remote_submitted'] ?? false)) {
            return null;
        }

        $clearedXml = $signed['cleared_xml'] ?? null;
        $status = strtolower((string) ($signed['reporting_status'] ?? ''));

        if (filled($clearedXml) && in_array($status, ['cleared', 'clear', 'accepted', 'reported'], true)) {
            return null;
        }

        if (in_array($status, ['cleared', 'clear', 'accepted'], true) && filled($signed['xml'] ?? null)) {
            return null;
        }

        $remote = $signed['remote_submission'] ?? null;
        if (is_array($remote)) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                $value = $remote[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        if ($status !== '' && ! in_array($status, HubInvoiceZatcaTab::successReportingStatuses(), true)) {
            return __('invoices.zatca_error_clear_status', ['status' => $status]);
        }

        if (! filled($clearedXml)) {
            return __('invoices.zatca_error_clear_failed');
        }

        return null;
    }
}