<?php

namespace App\Services;

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceSigningException;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Inventory\ProductStockService;
use App\Services\Invoicing\InvoiceIssueResult;
use App\Services\Invoicing\Signing\InvoiceSigningGatewayFactory;
use App\Services\Invoicing\Signing\InvoiceSigningRequestFactory;
use App\Support\Invoicing\InvoiceZatcaSubmissionGuard;
use App\Support\Invoicing\StandardInvoiceBuyerSnapshotRefresh;
use App\Support\Invoicing\StandardInvoiceZatcaPayloadPrecheck;
use App\Support\Invoicing\ZHeartApiValidationErrors;
use App\Support\Invoicing\ZatcaSubmissionPayloadPersistence;
use App\Support\Hub\HubInvoiceZatcaTab;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $gateway = $this->signingFactory->gateway();
        $autoSubmitZatca = config('zheart.auto_submit_signed') && $business->invoicingIntegrationEnabled();

        $now = Carbon::now('UTC');
        $referenced = $invoice->referencedInvoice;
        $invoiceCategory = $this->resolveInvoiceCategory($invoice, $referenced);
        $invoiceUuid = (string) (($invoice->payload['invoice_uuid'] ?? null) ?: Str::uuid());

        $payload = $invoice->payload ?? [];

        $dueDate = $this->resolveDueDate($invoice, $now, $business);

        if ($invoiceCategory === 'standard' && $business->invoicingIntegrationEnabled()) {
            $refreshed = StandardInvoiceBuyerSnapshotRefresh::mergeMissingFromCustomer($invoice);
            if ($refreshed !== $payload) {
                Invoice::withoutEvents(function () use ($invoice, $refreshed): void {
                    $invoice->forceFill(['payload' => $refreshed])->save();
                });
                $invoice->refresh();
                $payload = $refreshed;
            }

            $precheck = StandardInvoiceZatcaPayloadPrecheck::missingFields($invoice->fresh() ?? $invoice);
            if ($precheck !== []) {
                $summary = __('invoices.zatca_precheck.failed');
                Log::warning('invoice.zatca_issue_failed', [
                    'invoice_id' => $invoice->id,
                    'document_number' => $invoice->document_number,
                    'code' => 'precheck',
                    'field_error_count' => count($precheck),
                ]);

                return $this->finalizeIssuedAfterZatcaFailure(
                    $invoice,
                    $now,
                    $dueDate,
                    $invoiceUuid,
                    $summary,
                    $precheck,
                    null,
                    null,
                    ZatcaSubmissionPayloadPersistence::flashMessageForFailure($summary, $precheck),
                    'precheck',
                );
            }
        }

        $payload['invoice_uuid'] = $invoiceUuid;
        $signed = null;
        $signResult = null;

        Log::info('invoice.zatca_issue_request', [
            'invoice_id' => $invoice->id,
            'document_number' => $invoice->document_number,
            'invoice_category' => $invoiceCategory,
            'submit_path' => $invoiceCategory === 'simplified' ? 'invoices/report' : 'invoices/clear',
            'deferred_clearance' => $autoSubmitZatca,
        ]);

        try {
            DB::transaction(function () use (
                $invoice,
                $business,
                $gateway,
                $autoSubmitZatca,
                $now,
                $dueDate,
                $payload,
                $invoiceUuid,
                &$signResult,
            ): void {
                $invoice->forceFill([
                    'status' => InvoiceStatus::Issued,
                    'issued_at' => $now,
                    'due_date' => $dueDate,
                    'payload' => $payload,
                ]);
                $invoice->save();
                $invoice->refresh();

                $request = InvoiceSigningRequestFactory::fromIssuedInvoice($invoice, $business);

                InvoiceZatcaSubmissionGuard::markSubmissionInFlight($invoice);

                $localSign = $autoSubmitZatca
                    ? $gateway->signLocally($request)
                    : $gateway->signIssuedInvoice($request, $invoice);

                $signResult = $localSign;

                $mergedPayload = array_merge($payload, [
                    'invoice_uuid' => (string) ($localSign['invoice_uuid'] ?? $invoiceUuid),
                ]);

                Invoice::withoutEvents(function () use (
                    $invoice,
                    $localSign,
                    $mergedPayload,
                    $now,
                    $dueDate,
                ): void {
                    $invoice->forceFill([
                        'status' => InvoiceStatus::Issued,
                        'issued_at' => $now,
                        'due_date' => $dueDate,
                        'icv' => $localSign['icv'],
                        'invoice_hash' => $localSign['hash'],
                        'previous_invoice_hash' => $localSign['previous_invoice_hash'],
                        'signed_xml' => $localSign['xml'],
                        'qr_tlv_base64' => $localSign['qr'],
                        'zheart_seller' => is_array($localSign['seller'] ?? null) ? $localSign['seller'] : null,
                        'payload' => $mergedPayload,
                    ])->save();
                });

            });

            if ($autoSubmitZatca) {
                $fresh = $invoice->fresh() ?? $invoice;
                $clearRequest = InvoiceSigningRequestFactory::fromIssuedInvoice($fresh, $business);
                $signed = $gateway->submitInvoiceToZatca($clearRequest);
            } else {
                $signed = $signResult;
            }
        } catch (InvoiceSigningException $e) {
            $persisted = $invoice->fresh() ?? $invoice;
            InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($persisted);

            return $this->handleIssueSigningException($persisted, $e, $now, $dueDate, $invoiceUuid);
        } catch (\Throwable $e) {
            $persisted = $invoice->fresh() ?? $invoice;
            InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($persisted);

            return $this->handleIssueSigningException(
                $persisted,
                InvoiceSigningException::signingFailed($e),
                $now,
                $dueDate,
                $invoiceUuid,
            );
        }

        InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice->fresh() ?? $invoice);

        if ($signed === null) {
            throw InvoiceSigningException::signingFailed(context: 'Z-Heart clearance did not run after issue.');
        }

        $clearanceError = InvoiceZatcaSubmissionGuard::standardClearanceErrorMessage($signed, $invoiceCategory);

        $payloadMerge = array_merge($invoice->payload ?? [], [
            'lines' => $invoice->payload['lines'] ?? [],
        ]);
        $payloadMerge = ZatcaSubmissionPayloadPersistence::mergeAfterRemoteSubmission(
            $payloadMerge,
            $signed,
            unsetLastError: $clearanceError === null,
        );

        if ($clearanceError !== null) {
            $payloadMerge['zheart_last_error'] = $clearanceError;
            $payloadMerge['zheart_last_error_at'] = now()->toIso8601String();
        }

        $invoice->refresh();

        Invoice::withoutEvents(function () use ($invoice, $signed, $payloadMerge): void {
            $invoice->forceFill([
                'signed_xml' => $signed['cleared_xml'] ?? $signed['xml'] ?? $invoice->signed_xml,
                'qr_tlv_base64' => filled($signed['qr'] ?? null) ? $signed['qr'] : $invoice->qr_tlv_base64,
                'zheart_seller' => is_array($signed['seller'] ?? null) ? $signed['seller'] : $invoice->zheart_seller,
                'payload' => $payloadMerge,
            ])->save();
        });

        $this->auditService->record($invoice, 'invoice.issued', [
            'icv' => $signed['icv'],
            'invoice_hash' => $signed['hash'],
        ]);

        $issued = $invoice->fresh();
        $this->productStock->syncFromIssuedInvoice($issued);

        $signingSucceeded = ZatcaSubmissionPayloadPersistence::remoteSigningSucceeded($business, $issued);
        $userMessage = $signingSucceeded
            ? null
            : $this->issueZatcaUserMessage($issued);

        if (! $signingSucceeded) {
            Log::warning('invoice.zatca_issue_outcome', [
                'invoice_id' => $issued->id,
                'document_number' => $issued->document_number,
                'request_id' => data_get($issued->payload, 'zheart_request_id'),
                'http_status' => data_get($issued->payload, 'zheart_zatca_http_status')
                    ?? data_get($issued->payload, 'zheart_last_http_status'),
                'reporting_status' => data_get($issued->payload, 'zheart_reporting_status'),
            ]);
        }

        return new InvoiceIssueResult($issued, $signingSucceeded, $userMessage);
    }

    /**
     * @param  list<string>  $fieldErrors
     */
    private function handleIssueSigningException(
        Invoice $invoice,
        InvoiceSigningException $exception,
        Carbon $issuedAt,
        string $dueDate,
        string $invoiceUuid,
    ): InvoiceIssueResult {
        $invoice = $invoice->fresh() ?? $invoice;
        $parsed = ZatcaSubmissionPayloadPersistence::parseSigningException($exception);
        $previous = $exception->getPrevious();
        $errorCodes = $previous instanceof \App\Services\Invoicing\ZHeart\ZHeartApiException
            ? ZHeartApiValidationErrors::codesForLog($previous->responseJson)
            : [];

        Log::warning('invoice.zatca_issue_failed', [
            'invoice_id' => $invoice->id,
            'document_number' => $invoice->document_number,
            'http_status' => $parsed['http_status'],
            'request_id' => $parsed['request_id'],
            'field_error_count' => count($parsed['field_errors']),
            'zatca_error_codes' => $errorCodes,
        ]);

        $fresh = $invoice->fresh() ?? $invoice;
        if ($fresh->isIssued()) {
            return $this->finalizeIssuedAfterZatcaFailure(
                $fresh,
                $fresh->issued_at ?? $issuedAt,
                (string) ($fresh->due_date?->format('Y-m-d') ?? $dueDate),
                $invoiceUuid,
                $parsed['user_message'],
                $parsed['field_errors'],
                $parsed['http_status'],
                $parsed['request_id'],
                ZatcaSubmissionPayloadPersistence::flashMessageForFailure($parsed['user_message'], $parsed['field_errors']),
                'api_error',
                $parsed['remote_message'],
                preserveSigningArtifacts: true,
            );
        }

        return $this->finalizeIssuedAfterZatcaFailure(
            $invoice,
            $issuedAt,
            $dueDate,
            $invoiceUuid,
            $parsed['user_message'],
            $parsed['field_errors'],
            $parsed['http_status'],
            $parsed['request_id'],
            ZatcaSubmissionPayloadPersistence::flashMessageForFailure($parsed['user_message'], $parsed['field_errors']),
            'api_error',
            $parsed['remote_message'],
        );
    }

    private function finalizeIssuedAfterZatcaFailure(
        Invoice $invoice,
        Carbon $issuedAt,
        string $dueDate,
        string $invoiceUuid,
        string $summary,
        array $fieldErrors,
        ?int $httpStatus,
        ?string $requestId,
        string $flashMessage,
        string $code,
        ?string $remoteMessage = null,
        bool $preserveSigningArtifacts = false,
    ): InvoiceIssueResult {
        $payloadMerge = array_merge($invoice->payload ?? [], [
            'lines' => $invoice->payload['lines'] ?? [],
            'invoice_uuid' => $invoiceUuid,
        ]);
        $payloadMerge = ZatcaSubmissionPayloadPersistence::mergeAfterValidationFailure(
            $payloadMerge,
            $summary,
            $fieldErrors,
            $httpStatus,
            $requestId,
            $remoteMessage,
        );

        $attributes = [
            'status' => InvoiceStatus::Issued,
            'payload' => $payloadMerge,
        ];

        if ($preserveSigningArtifacts) {
            $attributes['due_date'] = $dueDate;
        } else {
            $attributes = array_merge($attributes, [
                'icv' => null,
                'invoice_hash' => null,
                'previous_invoice_hash' => null,
                'signed_xml' => null,
                'qr_tlv_base64' => null,
                'zheart_seller' => null,
                'issued_at' => $issuedAt,
                'due_date' => $dueDate,
            ]);
        }

        Invoice::withoutEvents(function () use ($invoice, $attributes): void {
            $invoice->forceFill($attributes)->save();
        });

        $this->auditService->record($invoice, 'invoice.issued', [
            'signing_deferred' => true,
            'zatca_issue_failure' => $code,
        ]);

        $issued = $invoice->fresh();
        $this->productStock->syncFromIssuedInvoice($issued);

        return new InvoiceIssueResult($issued, false, $flashMessage);
    }

    private function issueZatcaUserMessage(Invoice $issued): string
    {
        $fieldErrors = data_get($issued->payload, 'zheart_field_errors');
        $summary = HubInvoiceZatcaTab::shortError($issued)
            ?? (string) data_get($issued->payload, 'zheart_last_error', __('invoices.zatca_resubmit_failed'));

        if (is_array($fieldErrors) && $fieldErrors !== []) {
            return ZatcaSubmissionPayloadPersistence::flashMessageForFailure($summary, $fieldErrors);
        }

        return $summary !== ''
            ? $summary
            : __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]);
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
}
