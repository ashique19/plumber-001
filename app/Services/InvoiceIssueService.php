<?php

namespace App\Services;

use App\Business\BusinessContext;
use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceSigningException;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Inventory\ProductStockService;
use App\Services\Invoicing\InvoiceIssueResult;
use App\Services\Invoicing\Signing\InvoiceSigningGatewayFactory;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Support\Invoicing\SigningFailureSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceIssueService
{
    public function __construct(
        private readonly InvoiceSigningGatewayFactory $signingFactory,
        private readonly InvoiceAuditService $auditService,
        private readonly ProductStockService $productStock,
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

    public function issue(Invoice $invoice): InvoiceIssueResult
    {
        if ($invoice->isIssued()) {
            return new InvoiceIssueResult($invoice, true);
        }

        $business = BusinessContext::require();
        $lines = $this->linesFromInvoice($invoice);

        $now = Carbon::now('UTC');
        $referenced = $invoice->referencedInvoice;
        $invoiceCategory = $this->resolveInvoiceCategory($invoice, $referenced);
        $invoiceUuid = (string) (($invoice->payload['invoice_uuid'] ?? null) ?: Str::uuid());

        $payload = $invoice->payload ?? [];
        $documentDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);

        $signingRequest = new InvoiceSigningRequest(
            business: $business,
            documentNumber: $invoice->document_number,
            documentType: $invoice->document_type,
            invoiceCategory: $invoiceCategory,
            lines: $lines,
            invoicePayload: $payload,
            issueDateTimeUtc: $now,
            invoiceUuid: $invoiceUuid,
            referencedInvoiceNumber: $referenced?->document_number,
            referencedInvoiceUuid: $referenced
                ? (string) ($referenced->payload['invoice_uuid'] ?? '')
                : null,
            noteReason: $this->resolveNoteReason($invoice),
            documentDiscountExVat: $documentDiscount,
        );

        $dueDate = $this->resolveDueDate($invoice, $now, $business);

        try {
            $signed = $this->signingFactory->gateway()->signIssuedInvoice($signingRequest, $invoice);
        } catch (InvoiceSigningException $e) {
            return $this->finalizeWithoutRemoteSigning($invoice, $now, $dueDate, $invoiceUuid, $e);
        } catch (\Throwable $e) {
            $wrapped = InvoiceSigningException::signingFailed($e);

            return $this->finalizeWithoutRemoteSigning($invoice, $now, $dueDate, $invoiceUuid, $wrapped);
        }

        $payloadMerge = array_merge($invoice->payload ?? [], [
            'lines' => $invoice->payload['lines'] ?? [],
            'invoice_uuid' => $signed['invoice_uuid'],
            'signing_submission_status' => ($signed['remote_submitted'] ?? false) ? 'submitted' : 'signed_locally',
        ]);

        if (! empty($signed['request_id'])) {
            $payloadMerge['zheart_request_id'] = $signed['request_id'];
        }

        if (! empty($signed['remote_submission'])) {
            $payloadMerge['zheart_zatca'] = $signed['remote_submission'];
        }

        if (filled($signed['reporting_status'] ?? null)) {
            $payloadMerge['zheart_reporting_status'] = $signed['reporting_status'];
        }

        $payloadMerge['zheart_remote_submitted'] = (bool) ($signed['remote_submitted'] ?? false);

        $invoice->forceFill([
            'status' => InvoiceStatus::Issued,
            'icv' => $signed['icv'],
            'invoice_hash' => $signed['hash'],
            'previous_invoice_hash' => $signed['previous_invoice_hash'],
            'signed_xml' => $signed['cleared_xml'] ?? $signed['xml'],
            'qr_tlv_base64' => $signed['qr'],
            'issued_at' => $now,
            'due_date' => $dueDate,
            'payload' => $payloadMerge,
        ])->save();

        $this->auditService->record($invoice, 'invoice.issued', [
            'icv' => $signed['icv'],
            'invoice_hash' => $signed['hash'],
        ]);

        $issued = $invoice->fresh();
        $this->productStock->syncFromIssuedInvoice($issued);

        return new InvoiceIssueResult($issued, true);
    }

    private function finalizeWithoutRemoteSigning(
        Invoice $invoice,
        Carbon $issuedAt,
        string $dueDate,
        string $invoiceUuid,
        InvoiceSigningException $exception,
    ): InvoiceIssueResult {
        Log::warning('invoice.signing_deferred', array_merge(
            SigningFailureSanitizer::logContext($exception),
            [
                'invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
            ],
        ));

        $payloadMerge = array_merge($invoice->payload ?? [], [
            'lines' => $invoice->payload['lines'] ?? [],
            'invoice_uuid' => $invoiceUuid,
            'signing_submission_status' => 'not_submitted',
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
        ]);

        $invoice->forceFill([
            'status' => InvoiceStatus::Issued,
            'icv' => null,
            'invoice_hash' => null,
            'previous_invoice_hash' => null,
            'signed_xml' => null,
            'qr_tlv_base64' => null,
            'issued_at' => $issuedAt,
            'due_date' => $dueDate,
            'payload' => $payloadMerge,
        ])->save();

        $this->auditService->record($invoice, 'invoice.issued', [
            'signing_deferred' => true,
        ]);

        $issued = $invoice->fresh();
        $this->productStock->syncFromIssuedInvoice($issued);

        $userMessage = SigningFailureSanitizer::sanitizeMessage($exception->getMessage());

        return new InvoiceIssueResult($issued, false, $userMessage);
    }

    private function resolveDueDate(Invoice $invoice, Carbon $now, BusinessSetting $business): string
    {
        $payloadDue = trim((string) ($invoice->payload['buyer_due_date'] ?? ''));
        if ($payloadDue !== '') {
            try {
                return Carbon::parse($payloadDue, 'Asia/Riyadh')->toDateString();
            } catch (\Throwable) {
                // Fall through to payment terms.
            }
        }

        $termsDays = max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30));

        return $now->copy()->timezone('Asia/Riyadh')->addDays($termsDays)->toDateString();
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
            $nameAr = trim((string) ($line['name_ar'] ?? ''));

            return new InvoiceLineData(
                name: (string) $line['name'],
                quantity: (float) $line['quantity'],
                unitPriceExVat: (float) $line['unit_price_ex_vat'],
                vatRate: (float) ($line['vat_rate'] ?? 15),
                discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
                nameAr: $nameAr !== '' ? $nameAr : null,
            );
        }, is_array($raw) ? $raw : []);
    }
}
