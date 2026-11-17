<?php

namespace App\Services\Invoicing\Signing;

use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\InvoiceLineDataFactory;
use Illuminate\Support\Carbon;

final class InvoiceSigningRequestFactory
{
    public static function fromIssuedInvoice(Invoice $invoice, BusinessSetting $business): InvoiceSigningRequest
    {
        $issuedAt = $invoice->issued_at ?? Carbon::now('UTC');
        $referenced = $invoice->referencedInvoice;
        $payload = $invoice->payload ?? [];
        $invoiceUuid = (string) (($payload['invoice_uuid'] ?? null) ?: '');

        if ($invoiceUuid === '') {
            throw new \InvalidArgumentException('Invoice UUID missing on issued document.');
        }

        return new InvoiceSigningRequest(
            business: $business,
            documentNumber: $invoice->document_number,
            documentType: $invoice->document_type,
            invoiceCategory: self::resolveInvoiceCategory($invoice, $referenced),
            lines: self::linesFromInvoice($invoice),
            invoicePayload: $payload,
            issueDateTimeUtc: $issuedAt->copy()->utc(),
            invoiceUuid: $invoiceUuid,
            referencedInvoiceNumber: $referenced?->document_number,
            referencedInvoiceUuid: $referenced
                ? (string) ($referenced->payload['invoice_uuid'] ?? '')
                : null,
            noteReason: self::resolveNoteReason($invoice),
            documentDiscountExVat: (float) ($payload['invoice_discount_ex_vat'] ?? 0),
        );
    }

    private static function resolveInvoiceCategory(Invoice $invoice, ?Invoice $referenced): string
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

    private static function resolveNoteReason(Invoice $invoice): ?string
    {
        $reason = $invoice->payload['refund_reason'] ?? $invoice->payload['note_reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * @return list<\App\Data\InvoiceLineData>
     */
    private static function linesFromInvoice(Invoice $invoice): array
    {
        $raw = $invoice->payload['lines'] ?? [];

        return InvoiceLineDataFactory::fromPayloadLines(is_array($raw) ? $raw : []);
    }
}