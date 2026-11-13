<?php

namespace App\Services;

use App\Business\BusinessContext;
use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Invoicing\Signing\InvoiceSigningGatewayFactory;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InvoiceIssueService
{
    public function __construct(
        private readonly InvoiceSigningGatewayFactory $signingFactory,
        private readonly InvoiceAuditService $auditService,
    ) {}

    /**
     * @param  list<array{name: string, quantity: float, unit_price_ex_vat: float, vat_rate: float}>  $lineItems
     */
    public function issueDraft(
        ?Branch $branch,
        string $documentNumber,
        InvoiceDocumentType $type,
        array $lineItems,
        ?Invoice $referencedInvoice = null,
    ): Invoice {
        $invoice = Invoice::query()->create([
            'branch_id' => $branch?->id,
            'document_number' => $documentNumber,
            'document_type' => $type,
            'status' => InvoiceStatus::Draft,
            'referenced_invoice_id' => $referencedInvoice?->id,
            'payload' => [
                'lines' => $lineItems,
            ],
        ]);

        $this->auditService->record($invoice, 'invoice.draft_created', [
            'document_type' => $type->value,
        ]);

        return $invoice;
    }

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->isIssued()) {
            return $invoice;
        }

        $business = BusinessContext::require();
        $lines = $this->linesFromInvoice($invoice);

        $now = Carbon::now('UTC');
        $referenced = $invoice->referencedInvoice;
        $invoiceCategory = $this->resolveInvoiceCategory($invoice, $referenced);
        $invoiceUuid = (string) (($invoice->payload['invoice_uuid'] ?? null) ?: Str::uuid());

        $signingRequest = new InvoiceSigningRequest(
            business: $business,
            documentNumber: $invoice->document_number,
            documentType: $invoice->document_type,
            invoiceCategory: $invoiceCategory,
            lines: $lines,
            invoicePayload: $invoice->payload ?? [],
            issueDateTimeUtc: $now,
            invoiceUuid: $invoiceUuid,
            referencedInvoiceNumber: $referenced?->document_number,
            referencedInvoiceUuid: $referenced
                ? (string) ($referenced->payload['invoice_uuid'] ?? '')
                : null,
            noteReason: $this->resolveNoteReason($invoice),
        );

        $signed = $this->signingFactory->gateway()->signIssuedInvoice($signingRequest, $invoice);

        $payloadMerge = array_merge($invoice->payload ?? [], [
            'lines' => $invoice->payload['lines'] ?? [],
            'invoice_uuid' => $signed['invoice_uuid'],
        ]);

        if (! empty($signed['request_id'])) {
            $payloadMerge['zheart_request_id'] = $signed['request_id'];
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Issued,
            'icv' => $signed['icv'],
            'invoice_hash' => $signed['hash'],
            'previous_invoice_hash' => $signed['previous_invoice_hash'],
            'signed_xml' => $signed['cleared_xml'] ?? $signed['xml'],
            'qr_tlv_base64' => $signed['qr'],
            'issued_at' => $now,
            'payload' => $payloadMerge,
        ])->save();

        $this->auditService->record($invoice, 'invoice.issued', [
            'icv' => $signed['icv'],
            'invoice_hash' => $signed['hash'],
        ]);

        return $invoice->fresh();
    }

    private function resolveInvoiceCategory(Invoice $invoice, ?Invoice $referenced): string
    {
        $fromPayload = $invoice->payload['invoice_category'] ?? null;
        if (is_string($fromPayload) && $fromPayload !== '') {
            return $fromPayload;
        }

        return match ($invoice->document_type) {
            InvoiceDocumentType::Simplified => 'simplified',
            InvoiceDocumentType::Standard => 'standard',
            InvoiceDocumentType::CreditNote, InvoiceDocumentType::DebitNote => match ($referenced?->document_type) {
                InvoiceDocumentType::Simplified => 'simplified',
                default => 'standard',
            },
        };
    }

    private function resolveNoteReason(Invoice $invoice): ?string
    {
        $reason = $invoice->payload['refund_reason'] ?? $invoice->payload['note_reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * @return list<InvoiceLineData>
     */
    private function linesFromInvoice(Invoice $invoice): array
    {
        $raw = $invoice->payload['lines'] ?? [];

        return array_map(function (array $line): InvoiceLineData {
            return new InvoiceLineData(
                name: (string) $line['name'],
                quantity: (float) $line['quantity'],
                unitPriceExVat: (float) $line['unit_price_ex_vat'],
                vatRate: (float) ($line['vat_rate'] ?? 15),
                discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
            );
        }, is_array($raw) ? $raw : []);
    }
}
