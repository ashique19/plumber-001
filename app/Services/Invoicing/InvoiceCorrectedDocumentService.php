<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Services\InvoiceIssueService;
use Illuminate\Support\Str;

final class InvoiceCorrectedDocumentService
{
    public function __construct(
        private readonly InvoiceIssueService $issuer,
    ) {}

    /**
     * Credit note against a rejected simplified invoice, then the caller should issue a new invoice.
     */
    public function issueCreditNote(Invoice $rejected, ?string $reason = null): InvoiceIssueResult
    {
        $this->assertRejected($rejected);

        $reason = $reason !== null && trim($reason) !== ''
            ? trim($reason)
            : __('invoices.correction.credit_reason', ['number' => $rejected->officialOrDocumentNumber()]);

        $draft = $this->issuer->issueDraft(
            branch: $rejected->branch,
            documentNumber: 'CN-'.$rejected->document_number.'-'.Str::upper(Str::random(4)),
            type: InvoiceDocumentType::CreditNote,
            lineItems: $this->clonedLines($rejected),
            referencedInvoice: $rejected,
        );

        $payload = array_merge($draft->payload ?? [], $this->copiedBuyerPayload($rejected), [
            'invoice_category' => ((string) data_get($rejected->payload, 'invoice_category') === 'simplified' || $rejected->document_type === InvoiceDocumentType::Simplified) ? 'simplified' : 'standard',
            'note_reason' => $reason,
            'refund_reason' => $reason,
            'corrected_from_invoice_id' => $rejected->id,
        ]);
        $draft->forceFill(['payload' => $payload])->save();

        return $this->issuer->issue($draft->fresh());
    }

    /**
     * New invoice (new UUID + new number series allocation) cloned from a rejected document.
     */
    public function issueCorrectedInvoice(Invoice $rejected): InvoiceIssueResult
    {
        $this->assertRejected($rejected);

        if ($rejected->document_type === InvoiceDocumentType::CreditNote) {
            throw new \InvalidArgumentException(__('invoices.errors.correct_credit_not_supported'));
        }

        $type = $rejected->document_type;
        $prefix = $type === InvoiceDocumentType::Standard ? 'STD' : 'SIMP';
        $draft = $this->issuer->issueDraft(
            branch: $rejected->branch,
            documentNumber: $prefix.'-COR-'.now('Asia/Riyadh')->format('Ymd-His').'-'.Str::upper(Str::random(3)),
            type: $type,
            lineItems: $this->clonedLines($rejected),
        );

        $payload = array_merge($draft->payload ?? [], $this->copiedBuyerPayload($rejected), [
            'invoice_category' => ((string) data_get($rejected->payload, 'invoice_category') === 'simplified' || $rejected->document_type === InvoiceDocumentType::Simplified) ? 'simplified' : 'standard',
            'corrected_from_invoice_id' => $rejected->id,
        ]);
        $draft->forceFill(['payload' => $payload])->save();

        return $this->issuer->issue($draft->fresh());
    }

    private function assertRejected(Invoice $invoice): void
    {
        if (! $invoice->isSubmissionRejected()) {
            throw new \InvalidArgumentException(__('invoices.errors.correct_only_rejected'));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clonedLines(Invoice $invoice): array
    {
        $lines = $invoice->payload['lines'] ?? [];

        return is_array($lines) ? $lines : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function copiedBuyerPayload(Invoice $invoice): array
    {
        $payload = is_array($invoice->payload) ? $invoice->payload : [];
        $keep = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && (str_starts_with($key, 'buyer_') || $key === 'invoice_discount_ex_vat')) {
                $keep[$key] = $value;
            }
        }

        return $keep;
    }
}
