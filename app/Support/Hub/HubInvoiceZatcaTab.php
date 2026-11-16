<?php

namespace App\Support\Hub;

use App\Models\Invoice;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use Illuminate\Database\Eloquent\Builder;

final class HubInvoiceZatcaTab
{
    public const ALL = 'all';

    public const FAILED = 'failed';

    public const SUCCESS = 'success';

    /**
     * @return list<string>
     */
    public static function successReportingStatuses(): array
    {
        return ['reported', 'cleared', 'clear', 'accepted', 'warning', 'warnings'];
    }

    /**
     * @return list<string>
     */
    public static function failedReportingStatuses(): array
    {
        return ['failed', 'rejected', 'error', 'pending_error', 'not_sent'];
    }

    public static function isSuccess(Invoice $invoice): bool
    {
        if (! $invoice->isIssued()) {
            return false;
        }

        if (data_get($invoice->payload, 'zheart_remote_submitted') !== true) {
            return false;
        }

        $reporting = strtolower((string) data_get($invoice->payload, 'zheart_reporting_status', ''));
        $zatcaStatus = strtolower((string) data_get($invoice->payload, 'zheart_zatca.status', ''));

        $effective = $zatcaStatus !== '' ? $zatcaStatus : $reporting;

        if (in_array($effective, self::successReportingStatuses(), true)) {
            return true;
        }

        return filled(data_get($invoice->payload, 'zheart_zatca'));
    }

    public static function isFailed(Invoice $invoice): bool
    {
        if (! $invoice->isIssued() || self::isSuccess($invoice)) {
            return false;
        }

        if (ZatcaSubmissionStatus::invoiceNotSentToZatca($invoice)) {
            return true;
        }

        $reporting = strtolower((string) data_get($invoice->payload, 'zheart_reporting_status', ''));
        $zatcaStatus = strtolower((string) data_get($invoice->payload, 'zheart_zatca.status', ''));
        $effective = $zatcaStatus !== '' ? $zatcaStatus : $reporting;

        if (in_array($effective, self::failedReportingStatuses(), true)) {
            return true;
        }

        if (filled(data_get($invoice->payload, 'zheart_last_error'))) {
            return true;
        }

        $submission = (string) data_get($invoice->payload, 'signing_submission_status', '');

        return in_array($submission, ['not_submitted', 'signed_locally'], true)
            || (filled($invoice->signed_xml) && data_get($invoice->payload, 'zheart_remote_submitted') !== true);
    }

    public static function shortError(Invoice $invoice): ?string
    {
        $message = data_get($invoice->payload, 'zheart_last_error');
        if (is_string($message) && $message !== '') {
            return $message;
        }

        $zatca = data_get($invoice->payload, 'zheart_zatca');
        if (is_array($zatca)) {
            foreach (['message', 'error', 'detail'] as $key) {
                $value = $zatca[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        if (ZatcaSubmissionStatus::invoiceNotSentToZatca($invoice)) {
            return __('invoices.zatca_error_not_sent');
        }

        return null;
    }

    public static function canResend(Invoice $invoice): bool
    {
        if (! $invoice->isIssued() || ! ZatcaSubmissionStatus::canSubmitToZatca()) {
            return false;
        }

        if (self::isSuccess($invoice)) {
            return false;
        }

        $badge = HubInvoiceZatcaBadge::badgeKey($invoice);

        if (in_array($badge, [HubInvoiceZatcaBadge::Rejected, HubInvoiceZatcaBadge::NotSubmitted], true)) {
            return true;
        }

        return self::isFailed($invoice) && $badge !== HubInvoiceZatcaBadge::Pending;
    }

    public static function applyTabScope(Builder $query, string $tab): void
    {
        if ($tab === self::SUCCESS) {
            self::applySuccessScope($query);

            return;
        }

        if ($tab === self::FAILED) {
            self::applyFailedScope($query);
        }
    }

    public static function applySuccessScope(Builder $query): void
    {
        $success = self::successReportingStatuses();

        $query->where('payload->zheart_remote_submitted', true)
            ->where(function (Builder $q) use ($success): void {
                $q->whereIn('payload->zheart_reporting_status', $success)
                    ->orWhereIn('payload->zheart_zatca->status', $success)
                    ->orWhereNotNull('payload->zheart_zatca');
            });
    }

    public static function applyFailedScope(Builder $query): void
    {
        $failedStatuses = self::failedReportingStatuses();

        $query->where(function (Builder $outer): void {
            $outer->whereNot(function (Builder $success): void {
                self::applySuccessScope($success);
            });
        })->where(function (Builder $q) use ($failedStatuses): void {
            $q->where('payload->signing_submission_status', 'not_submitted')
                ->orWhere('payload->signing_submission_status', 'signed_locally')
                ->orWhere('payload->zheart_remote_submitted', false)
                ->orWhereIn('payload->zheart_reporting_status', $failedStatuses)
                ->orWhereIn('payload->zheart_zatca->status', $failedStatuses)
                ->orWhereNotNull('payload->zheart_last_error')
                ->orWhere(function (Builder $signedOnly): void {
                    $signedOnly->whereNotNull('signed_xml')
                        ->where('signed_xml', '!=', '')
                        ->where(function (Builder $notRemote): void {
                            $notRemote->whereNull('payload->zheart_remote_submitted')
                                ->orWhere('payload->zheart_remote_submitted', false);
                        });
                });
        });
    }

    public static function countFailed(): int
    {
        $query = Invoice::query()->where('status', 'issued');
        self::applyFailedScope($query);

        return (int) $query->count();
    }

    public static function countSuccess(): int
    {
        $query = Invoice::query()->where('status', 'issued');
        self::applySuccessScope($query);

        return (int) $query->count();
    }
}