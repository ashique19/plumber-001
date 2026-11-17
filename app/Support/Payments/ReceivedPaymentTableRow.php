<?php

namespace App\Support\Payments;

use App\Models\InvoicePayment;
use App\Support\Hub\HubInvoicePrintLinks;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Invoicing\InvoiceCustomerIdentity;

final class ReceivedPaymentTableRow
{
    /**
     * @return array<string, mixed>
     */
    public static function fromPayment(InvoicePayment $payment): array
    {
        $invoice = $payment->invoice;
        $printUrls = $invoice !== null ? HubInvoicePrintLinks::urls($invoice) : [];
        $actions = $invoice !== null ? HubInvoiceRowActions::forInvoice($invoice, 0.0) : [];

        $viewUrl = null;
        if ($invoice !== null) {
            $viewUrl = ($actions['edit'] ?? false)
                ? (string) ($actions['edit_url'] ?? '')
                : ($printUrls['view'] ?? null);
        }

        $receiptUrl = null;
        if ($invoice !== null && HubInvoiceRowActions::canDownloadOrPrint($invoice)) {
            $receiptUrl = $printUrls['cash'] ?? null;
        }

        return [
            'payment' => $payment,
            'document_number' => $invoice?->document_number ?? '—',
            'customer' => self::customerLabel($payment),
            'method' => $payment->method,
            'amount' => (float) $payment->amount,
            'paid_on' => $payment->paid_on,
            'view_url' => $viewUrl,
            'receipt_url' => $receiptUrl,
            'invoice_id' => $invoice?->id,
            'for_retention' => (bool) $payment->for_retention,
        ];
    }

    private static function customerLabel(InvoicePayment $payment): string
    {
        if ($payment->customer !== null && trim($payment->customer->name) !== '') {
            return trim($payment->customer->name);
        }

        if ($payment->invoice !== null) {
            $identity = InvoiceCustomerIdentity::resolve($payment->invoice);
            $name = trim((string) ($identity['name'] ?? ''));

            return $name !== '' ? $name : __('payments.unknown_customer');
        }

        return __('payments.unknown_customer');
    }
}