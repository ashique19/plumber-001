<?php

namespace App\Support\Invoicing;

use App\Models\Invoice;
use App\Support\Hub\HubInvoiceZatcaTab;

final class ZatcaSubmissionOutcome
{
    public const CLEARED = 'cleared';

    public const REPORTED = 'reported';

    public const WARNING = 'warning';

    public const REJECTED = 'rejected';

    public const PENDING = 'pending';

    private function __construct(
        public readonly string $code,
        public readonly bool $ok,
        public readonly string $flashLevel,
        public readonly string $userMessage,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $signed  Raw Z-Heart response (for logging only).
     */
    public static function fromPersistedInvoice(Invoice $invoice, array $signed, string $invoiceCategory): self
    {
        $number = $invoice->officialOrDocumentNumber();
        $outcome = \App\Support\Invoicing\ZHeartSubmissionOutcome::normalize(data_get($invoice->payload, 'zheart_outcome'));
        if ($outcome === \App\Support\Invoicing\ZHeartSubmissionOutcome::RETRY) {
            return new self(
                self::PENDING,
                false,
                'warning',
                __('invoices.issue_sending', ['number' => $number]),
                null,
            );
        }
        if ($outcome === \App\Support\Invoicing\ZHeartSubmissionOutcome::REJECTED) {
            $reason = HubInvoiceZatcaTab::shortError($invoice) ?? self::extractReason($signed) ?? __('invoices.zatca_resubmit_failed');

            return new self(
                self::REJECTED,
                false,
                'error',
                __('invoices.zatca_resubmit_rejected', ['number' => $number, 'reason' => $reason]),
                $reason,
            );
        }

        $status = InvoiceZatcaSubmissionGuard::effectiveReportingStatus($invoice);
        $reason = HubInvoiceZatcaTab::shortError($invoice) ?? self::extractReason($signed);

        if (in_array($status, ['warning', 'warnings'], true)) {
            $text = $reason ?? __('invoices.zatca_resubmit_warning', ['number' => $number]);

            return new self(self::WARNING, true, 'warning', $text, $reason);
        }

        if (HubInvoiceZatcaTab::isSuccess($invoice)) {
            if (in_array($status, ['cleared', 'clear', 'accepted'], true)
                || ($invoiceCategory === 'standard' && filled($signed['cleared_xml'] ?? null))) {
                return new self(
                    self::CLEARED,
                    true,
                    'success',
                    __('invoices.zatca_resubmit_cleared', ['number' => $number]),
                    $reason,
                );
            }

            return new self(
                self::REPORTED,
                true,
                'success',
                in_array($status, ['reported'], true)
                    ? __('invoices.zatca_resubmit_reported', ['number' => $number])
                    : __('invoices.zatca_resubmit_success', ['number' => $number]),
                $reason,
            );
        }

        if (filled(data_get($invoice->payload, 'zheart_last_error'))
            || in_array($status, HubInvoiceZatcaTab::failedReportingStatuses(), true)) {
            $detail = $reason ?? __('invoices.zatca_resubmit_failed');

            return new self(
                self::REJECTED,
                false,
                'error',
                __('invoices.zatca_resubmit_rejected', ['number' => $number, 'reason' => $detail]),
                $detail,
            );
        }

        if ($status === 'pending' || ($status === '' && data_get($invoice->payload, 'zheart_remote_submitted') === true)) {
            $detail = $reason ?? __('invoices.zatca_resubmit_still_pending_detail');

            return new self(
                self::PENDING,
                false,
                'warning',
                __('invoices.zatca_resubmit_still_pending', ['number' => $number, 'reason' => $detail]),
                $detail,
            );
        }

        return new self(
            self::PENDING,
            false,
            'warning',
            __('invoices.zatca_resubmit_still_pending', [
                'number' => $number,
                'reason' => $reason ?? __('invoices.zatca_resubmit_failed'),
            ]),
            $reason,
        );
    }

    /**
     * @param  array<string, mixed>  $signed
     */
    public static function extractReason(array $signed): ?string
    {
        $remote = $signed['remote_submission'] ?? null;
        if (! is_array($remote)) {
            return null;
        }

        foreach (['message', 'error', 'detail', 'description'] as $key) {
            $value = $remote[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $errors = $remote['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $first = $errors[0] ?? null;
            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }
        }

        $clearance = $remote['clearance_status'] ?? null;
        if (is_string($clearance) && trim($clearance) !== '') {
            return trim($clearance);
        }

        return null;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function logContext(Invoice $invoice, array $signed): array
    {
        return [
            'invoice_id' => $invoice->id,
            'document_number' => $invoice->document_number,
            'outcome' => $this->code,
            'reporting_status' => InvoiceZatcaSubmissionGuard::effectiveReportingStatus($invoice),
            'request_id' => $signed['request_id'] ?? null,
            'reason' => $this->reason,
        ];
    }
}