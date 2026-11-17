<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Invoicing\InvoicePayloadTotals;

final class HubInvoiceTableRow
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, InvoiceDuesLedgerService $ledger, ?string $returnQuery = null): array
    {
        $totals = InvoicePayloadTotals::forInvoice($invoice);
        $balance = $ledger->balanceFor($invoice);

        $customer = trim((string) data_get($invoice->payload, 'buyer_name', ''));
        $customerAr = trim((string) data_get($invoice->payload, 'buyer_name_ar', ''));
        if ($customer === '' && $customerAr !== '') {
            $customer = $customerAr;
        }

        $printUrls = HubInvoicePrintLinks::urls($invoice, $returnQuery);
        $dueAmount = $balance['principal_outstanding'];
        $actions = HubInvoiceRowActions::forInvoice($invoice, $dueAmount);
        $viewUrl = $actions['edit'] ? (string) $actions['edit_url'] : $printUrls['view'];
        $isDraft = $invoice->status === InvoiceStatus::Draft;

        return [
            'invoice' => $invoice,
            'display_number' => HubInvoiceListLabel::forHub($invoice),
            'row_url' => $viewUrl,
            'customer' => $customer !== '' ? $customer : '—',
            'total_inc_vat' => $totals['total_inc_vat'],
            'due_amount' => $isDraft ? null : $dueAmount,
            'payment_status' => $isDraft ? null : $balance['status'],
            'invoice_status_key' => self::invoiceStatusKey($invoice),
            'zatca_badge' => HubInvoiceZatcaBadge::badgeKey($invoice),
            'zatca_error' => HubInvoiceZatcaTab::shortError($invoice),
            'zatca_failed' => HubInvoiceZatcaTab::isFailed($invoice),
            'zatca_success' => HubInvoiceZatcaTab::isSuccess($invoice),
            'actions' => $actions,
            'view_url' => $viewUrl,
            'print_urls' => $printUrls,
            'more' => self::moreLinks($invoice, $actions),
        ];
    }

    public static function invoiceStatusKey(Invoice $invoice): string
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            return 'draft';
        }

        if ($invoice->status === InvoiceStatus::Voided) {
            return 'cancelled';
        }

        if ($invoice->status !== InvoiceStatus::Issued) {
            return 'draft';
        }

        return match ($invoice->document_type) {
            InvoiceDocumentType::CreditNote => 'credited',
            InvoiceDocumentType::DebitNote => 'returned',
            default => 'issued',
        };
    }

    /**
     * @param  array<string, mixed>  $actions
     * @return array<string, mixed>
     */
    private static function moreLinks(Invoice $invoice, array $actions): array
    {
        $paymentUrl = $actions['record_payment']
            ? route('admin.payments.dues', ['selectedInvoiceId' => $invoice->id])
            : null;

        $creditUrl = null;
        if ($invoice->isIssued() && $invoice->source_type === PosOrder::class && $invoice->source_id && $invoice->branch_id) {
            $creditUrl = route('admin.pos.history', ['branch' => $invoice->branch_id]);
        }

        $duplicateUrl = null;
        if ($invoice->isIssued()) {
            $duplicateMode = $invoice->document_type->value === 'standard' ? 'standard' : 'simplified';
            $duplicateUrl = route('admin.invoices.issue', ['mode' => $duplicateMode, 'duplicate_from' => $invoice->id]);
        }

        return [
            'record_payment' => $paymentUrl,
            'credit_note' => $creditUrl,
            'duplicate' => $duplicateUrl,
        ];
    }
}