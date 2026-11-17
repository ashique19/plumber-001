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
use App\Support\Invoicing\InvoiceLineDataFactory;
use App\Support\Invoicing\InvoiceZatcaSubmissionGuard;
use App\Support\Invoicing\SigningFailureSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        $lock = Cache::lock(InvoiceZatcaSubmissionGuard::lockKey((int) $invoice->id), InvoiceZatcaSubmissionGuard::LOCK_SECONDS);
        if (! $lock->get()) {
            $fresh = $invoice->fresh() ?? $invoice;
            if ($fresh->isIssued()) {
                return new InvoiceIssueResult($fresh, filled($fresh->signed_xml));
            }

            return new InvoiceIssueResult($invoice, false, __('invoices.errors.issue_in_progress'));
        }

        try {
            return $this->issueWithLock($invoice->fresh() ?? $invoice);
        } finally {
            $lock->release();
        }
    }

    private function issueWithLock(Invoice $invoice): InvoiceIssueResult
    {
        if ($invoice->isIssued()) {
            return new InvoiceIssueResult($invoice, true);
        }

        if (InvoiceZatcaSubmissionGuard::shouldSkipRemoteSubmission($invoice)) {
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

        InvoiceZatcaSubmissionGuard::markSubmissionInFlight($invoice);

        try {
            $signed = $this->signingFactory->gateway()->signIssuedInvoice($signingRequest, $invoice);
        } catch (InvoiceSigningException $e) {
            InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);

            return $this->finalizeWithoutRemoteSigning($invoice, $now, $dueDate, $invoiceUuid, $e);
        } catch (\Throwable $e) {
            InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);
            $wrapped = InvoiceSigningException::signingFailed($e);

            return $this->finalizeWithoutRemoteSigning($invoice, $now, $dueDate, $invoiceUuid, $wrapped);
        }

        InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);

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

        $clearanceError = InvoiceZatcaSubmissionGuard::standardClearanceErrorMessage($signed, $invoiceCategory);
        if ($clearanceError !== null) {
            $payloadMerge['zheart_last_error'] = $clearanceError;
            $payloadMerge['zheart_last_error_at'] = now()->toIso8601String();
        }

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
            'zheart_last_error' => SigningFailureSanitizer::sanitizeMessage($exception->getMessage()),
            'zheart_last_error_at' => now()->toIso8601String(),
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
        $payload = $invoice->payload ?? [];
        $rawTerms = trim((string) ($payload['payment_terms'] ?? ''));
        $payloadDue = trim((string) ($payload['buyer_due_date'] ?? ''));
        $termsCode = \App\Support\Invoicing\DocumentPaymentTerms::normalizeCode(
            $rawTerms,
            max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30)),
        );

        if ($rawTerms === '' && $payloadDue !== '') {
            try {
                return Carbon::parse($payloadDue, 'Asia/Riyadh')->toDateString();
            } catch (\Throwable) {
                // fall through
            }
        }

        if (\App\Support\Invoicing\DocumentPaymentTerms::isImmediate($termsCode)) {
            $supply = trim((string) ($payload['supply_date'] ?? ''));
            if ($supply !== '') {
                try {
                    return Carbon::parse($supply, 'Asia/Riyadh')->toDateString();
                } catch (\Throwable) {
                    // fall through
                }
            }

            return $now->copy()->timezone('Asia/Riyadh')->toDateString();
        }

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

        return InvoiceLineDataFactory::fromPayloadLines(is_array($raw) ? $raw : []);
    }
}
