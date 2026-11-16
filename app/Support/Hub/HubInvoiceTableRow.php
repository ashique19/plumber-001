<?php

namespace App\Support\Hub;

use App\Enums\InvoiceCollectionStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Invoicing\InvoicePayloadTotals;
use App\Support\Invoicing\InvoicePrintClearance;

final class HubInvoiceTableRow
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, InvoiceDuesLedgerService $ledger): array
    {
        $totals = InvoicePayloadTotals::forInvoice($invoice);
        $balance = $ledger->balanceFor($invoice);

        $customer = trim((string) data_get($invoice->payload, 'buyer_name', ''));
        $customerAr = trim((string) data_get($invoice->payload, 'buyer_name_ar', ''));
        if ($customer === '' && $customerAr !== '') {
            $customer = $customerAr;
        }

        $printUrls = HubInvoicePrintLinks::urls($invoice);
        $officialPrint = InvoicePrintClearance::allowsOfficialPrint($invoice);

        return [
            'invoice' => $invoice,
            'customer' => $customer !== '' ? $customer : '—',
            'total_inc_vat' => $totals['total_inc_vat'],
            'due_amount' => $balance['principal_outstanding'],
            'payment_status' => $balance['status'],
            'invoice_status_key' => self::invoiceStatusKey($invoice),
            'zatca_badge' => HubInvoiceZatcaBadge::badgeKey($invoice),
            'zatca_error' => HubInvoiceZatcaTab::shortError($invoice),
            'zatca_failed' => HubInvoiceZatcaTab::isFailed($invoice),
            'zatca_success' => HubInvoiceZatcaTab::isSuccess($invoice),
            'can_resend' => HubInvoiceZatcaTab::canResend($invoice),
            'print_urls' => $printUrls,
            'official_print' => $officialPrint,
            'more' => self::moreLinks($invoice),
        ];
    }

    public static function invoiceStatusKey(Invoice $invoice): string
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            return 'draft';
        }

        if ($invoice->status !== InvoiceStatus::Issued) {
            return 'draft';
        }

        if (! filled($invoice->signed_xml) || HubInvoiceZatcaBadge::badgeKey($invoice) === HubInvoiceZatcaBadge::Pending) {
            return 'pending';
        }

        return 'issued';
    }

    /**
     * @return array<string, mixed>
     */
    private static function moreLinks(Invoice $invoice): array
    {
        $paymentUrl = route('admin.payments.dues', ['selectedInvoiceId' => $invoice->id]);

        $creditUrl = null;
        if ($invoice->source_type === \App\Models\PosOrder::class && $invoice->source_id && $invoice->branch_id) {
            $creditUrl = route('admin.pos.history', ['branch' => $invoice->branch_id]);
        }

        $duplicateMode = $invoice->document_type->value === 'standard' ? 'standard' : 'simplified';
        $duplicateUrl = route('admin.invoices.issue', ['mode' => $duplicateMode, 'duplicate_from' => $invoice->id]);

        return [
            'record_payment' => $paymentUrl,
            'credit_note' => $creditUrl,
            'duplicate' => $duplicateUrl,
        ];
    }
}