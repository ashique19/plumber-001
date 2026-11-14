<?php

namespace App\Support\Invoicing;

use App\Models\BusinessSetting;
use App\Models\Invoice;

final class ZatcaSubmissionStatus
{
    public static function canSubmitToZatca(?BusinessSetting $business = null): bool
    {
        $business ??= BusinessSetting::query()->find(1) ?? BusinessSetting::singleton();

        $base = rtrim((string) config('zheart.base_url', ''), '/');
        $key = config('zheart.api_key');
        if ($base === '' || ! is_string($key) || $key === '') {
            return false;
        }

        return $business->invoicingIntegrationEnabled();
    }

    public static function invoiceNotSentToZatca(Invoice $invoice): bool
    {
        if (! $invoice->isIssued()) {
            return false;
        }

        $submission = $invoice->payload['signing_submission_status'] ?? null;
        if ($submission === 'not_submitted') {
            return true;
        }

        if (! filled($invoice->signed_xml)) {
            return true;
        }

        return data_get($invoice->payload, 'zheart_remote_submitted') === false
            && (string) data_get($invoice->payload, 'zheart_reporting_status', '') === 'pending';
    }
}