<?php

namespace App\Support;

use App\Models\Invoice;

/**
 * Human-readable Z-Heart / signing submission state for receipts (no compliance claims).
 */
final class ReceiptSigningStatusLabel
{
    public static function forInvoice(Invoice $invoice): array
    {
        $payload = $invoice->payload ?? [];
        $requestId = $payload['zheart_request_id'] ?? null;

        if (! config('zheart.auto_submit_signed')) {
            return [
                'tone' => 'sandbox',
                'title' => __('pos.receipt_status_sandbox_title'),
                'detail' => __('pos.receipt_status_sandbox_detail'),
            ];
        }

        if ($invoice->signed_xml === null || $invoice->signed_xml === '') {
            return [
                'tone' => 'not_reported',
                'title' => __('pos.receipt_status_not_reported_title'),
                'detail' => __('pos.receipt_status_not_reported_detail'),
            ];
        }

        if (is_string($requestId) && $requestId !== '') {
            return [
                'tone' => 'reported',
                'title' => __('pos.receipt_status_reported_title'),
                'detail' => __('pos.receipt_status_reported_detail'),
            ];
        }

        return [
            'tone' => 'not_reported',
            'title' => __('pos.receipt_status_not_reported_title'),
            'detail' => __('pos.receipt_status_pending_detail'),
        ];
    }
}
