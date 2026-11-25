<?php

namespace App\Support\Hub;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

final class HubInvoiceListLabel
{
    public static function forHub(Invoice $invoice): string
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice->displayNumber();
        }

        if (self::hasReservedFinalNumber($invoice)) {
            return (string) $invoice->document_number;
        }

        if (! self::usesProvisionalDraftNumber($invoice)) {
            return (string) $invoice->document_number;
        }

        $time = $invoice->created_at?->timezone('Asia/Riyadh')->format('Y-m-d H:i') ?? '—';

        return (string) __('invoices.draft_list_label', ['time' => $time]);
    }

    public static function hasReservedFinalNumber(Invoice $invoice): bool
    {
        if (filled($invoice->icv) || filled($invoice->invoice_hash)) {
            return true;
        }

        return data_get($invoice->payload, 'document_number_reserved') === true;
    }

    public static function usesProvisionalDraftNumber(Invoice $invoice): bool
    {
        $number = (string) $invoice->document_number;

        return preg_match('/^(SIMP|STD)-\d{8}-\d+$/', $number) === 1;
    }
}
