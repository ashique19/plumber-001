<?php

namespace App\Services\Invoicing;

use App\Business\BusinessContext;
use App\Exceptions\InvoiceSigningException;
use App\Models\Invoice;
use App\Services\InvoiceAuditService;
use App\Services\Invoicing\Signing\InvoiceSigningRequestFactory;
use App\Services\Invoicing\Signing\ZHeartInvoiceSigningGateway;
use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceZatcaTab;
use App\Support\Invoicing\SigningFailureSanitizer;
use App\Support\Invoicing\InvoiceZatcaSubmissionGuard;
use App\Support\Invoicing\StandardInvoiceBuyerSnapshotRefresh;
use App\Support\Invoicing\StandardInvoiceZatcaPayloadPrecheck;
use App\Support\Invoicing\ZHeartApiValidationErrors;
use App\Support\Invoicing\ZatcaResponseSanitizer;
use App\Support\Invoicing\ZatcaSubmissionOutcome;
use App\Support\Invoicing\ZatcaSubmissionPayloadPersistence;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class InvoiceZatcaResubmitService
{
    public function __construct(
        private readonly ZHeartInvoiceSigningGateway $gateway,
        private readonly InvoiceAuditService $auditService,
    ) {}

    public function resubmit(Invoice $invoice): InvoiceZatcaResubmitResult
    {
        if (! ZatcaSubmissionStatus::canSubmitToZatca()) {
            return InvoiceZatcaResubmitResult::notEligible();
        }

        if (! $invoice->isIssued()) {
            return InvoiceZatcaResubmitResult::notEligible();
        }

        if ($invoice->isSubmissionRejected()) {
            return InvoiceZatcaResubmitResult::failure(
                'rejected',
                __('invoices.errors.resubmit_rejected'),
                $invoice,
            );
        }

        if (InvoiceZatcaSubmissionGuard::shouldSkipResubmit($invoice)) {
            return InvoiceZatcaResubmitResult::alreadySuccess();
        }

        if (! HubInvoiceRowActions::canResendZatca($invoice)) {
            return InvoiceZatcaResubmitResult::notEligible();
        }

        $lock = Cache::lock(InvoiceZatcaSubmissionGuard::lockKey((int) $invoice->id), InvoiceZatcaSubmissionGuard::LOCK_SECONDS);

        if (! $lock->get()) {
            return InvoiceZatcaResubmitResult::locked();
        }

        try {
            $invoice->refresh();

            if (InvoiceZatcaSubmissionGuard::shouldSkipResubmit($invoice)) {
                return InvoiceZatcaResubmitResult::alreadySuccess();
            }

            if (InvoiceZatcaSubmissionGuard::submissionInFlight($invoice)) {
                InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);
                $invoice->refresh();
            }

            $business = BusinessContext::require();
            $request = InvoiceSigningRequestFactory::fromIssuedInvoice($invoice, $business);

            $viaZatcaApi = InvoiceZatcaSubmissionGuard::shouldResubmitViaZatcaApi($invoice);
            $preserveChain = filled($invoice->icv) && filled($invoice->invoice_hash);

            if ($viaZatcaApi) {
                $refreshedPayload = StandardInvoiceBuyerSnapshotRefresh::mergeMissingFromCustomer($invoice);
                if ($refreshedPayload !== ($invoice->payload ?? [])) {
                    $this->persistIssuedInvoice($invoice, ['payload' => $refreshedPayload]);
                    $invoice->refresh();
                }

                $precheck = StandardInvoiceZatcaPayloadPrecheck::missingFields($invoice);
                if ($precheck !== []) {
                    return $this->recordPrecheckFailure($invoice, $precheck);
                }
            }

            InvoiceZatcaSubmissionGuard::markSubmissionInFlight($invoice);

            try {
                Log::info('invoice.zatca_resubmit_request', [
                    'invoice_id' => $invoice->id,
                    'document_number' => $invoice->document_number,
                    'submit_path' => $viaZatcaApi
                        ? ($request->invoiceCategory === 'simplified' ? 'invoices/report' : 'invoices/clear')
                        : 'invoices/sign',
                ]);

                if ($viaZatcaApi) {
                    $signed = $this->gateway->submitInvoiceToZatca($request);
                } else {
                    $signed = $this->gateway->signIssuedInvoice($request, $invoice);
                    $preserveChain = false;
                }
            } catch (InvoiceSigningException $e) {
                InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);

                return $this->recordFailure($invoice, $e);
            } catch (\Throwable $e) {
                InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);

                return $this->recordFailure($invoice, InvoiceSigningException::signingFailed($e));
            }

            InvoiceZatcaSubmissionGuard::clearSubmissionInFlight($invoice);

            $merged = $this->mergeSubmissionPayload($invoice, $signed, $preserveChain);
            $category = (string) ($invoice->payload['invoice_category'] ?? $invoice->document_type->value);
            $clearanceError = InvoiceZatcaSubmissionGuard::standardClearanceErrorMessage($signed, $category);
            if ($clearanceError !== null) {
                $merged['zheart_last_error'] = $clearanceError;
                $merged['zheart_last_error_at'] = now()->toIso8601String();
            }
            $attributes = ['payload' => $merged];
            if (filled($signed['invoice_number'] ?? null)) {
                $attributes['official_number'] = $signed['invoice_number'];
            }

            if (! $preserveChain) {
                $attributes = array_merge($attributes, [
                    'icv' => $signed['icv'],
                    'invoice_hash' => $signed['hash'],
                    'previous_invoice_hash' => $signed['previous_invoice_hash'],
                    'signed_xml' => $signed['cleared_xml'] ?? $signed['xml'],
                    'qr_tlv_base64' => $signed['qr'],
                ]);
                if (is_array($signed['seller'] ?? null)) {
                    $attributes['zheart_seller'] = $signed['seller'];
                }
            } elseif (blank($invoice->qr_tlv_base64) && filled($signed['qr'] ?? null)) {
                $attributes['qr_tlv_base64'] = $signed['qr'];
            }
            if ($preserveChain && blank($invoice->zheart_seller) && is_array($signed['seller'] ?? null)) {
                $attributes['zheart_seller'] = $signed['seller'];
            }

            $this->persistIssuedInvoice($invoice, $attributes);

            $fresh = $invoice->fresh();
            $outcome = ZatcaSubmissionOutcome::fromPersistedInvoice($fresh, $signed, $category);
            $logContext = $outcome->logContext($fresh, $signed);

            if ($outcome->ok) {
                Log::info('invoice.zatca_resubmit_outcome', $logContext);
                $this->auditService->record($fresh, 'invoice.zatca_resubmit', [
                    'request_id' => $signed['request_id'] ?? null,
                    'reporting_status' => data_get($fresh->payload, 'zheart_reporting_status'),
                    'outcome' => $outcome->code,
                    'preserved_chain' => $preserveChain,
                ]);
            } else {
                Log::warning('invoice.zatca_resubmit_outcome', $logContext);
                $this->auditService->record($fresh, $outcome->code === ZatcaSubmissionOutcome::REJECTED
                    ? 'invoice.zatca_resubmit_failed'
                    : 'invoice.zatca_resubmit_pending', [
                    'request_id' => $signed['request_id'] ?? null,
                    'reporting_status' => data_get($fresh->payload, 'zheart_reporting_status'),
                    'outcome' => $outcome->code,
                    'message' => $outcome->reason,
                    'preserved_chain' => $preserveChain,
                ]);
            }

            return InvoiceZatcaResubmitResult::fromSubmissionOutcome($fresh, $outcome);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return list<InvoiceZatcaResubmitResult>
     */
    public function resubmitSequential(iterable $invoices): array
    {
        $results = [];

        foreach ($invoices as $invoice) {
            $results[] = $this->resubmit($invoice);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $signed
     * @return array<string, mixed>
     */
    private function mergeSubmissionPayload(Invoice $invoice, array $signed, bool $preserveChain): array
    {
        $payload = $invoice->payload ?? [];

        $payload = ZatcaSubmissionPayloadPersistence::mergeAfterRemoteSubmission($payload, $signed);

        if ($preserveChain) {
            unset($payload['invoice_uuid']);
            if (filled($invoice->payload['invoice_uuid'] ?? null)) {
                $payload['invoice_uuid'] = $invoice->payload['invoice_uuid'];
            }
        }

        return $payload;
    }

    /**
     * @param  list<string>  $fieldErrors
     */
    private function recordPrecheckFailure(Invoice $invoice, array $fieldErrors): InvoiceZatcaResubmitResult
    {
        $summary = __('invoices.zatca_precheck.failed');

        return $this->persistValidationFailure(
            $invoice,
            $summary,
            $fieldErrors,
            null,
            null,
            'precheck',
        );
    }

    private function recordFailure(Invoice $invoice, InvoiceSigningException $exception): InvoiceZatcaResubmitResult
    {
        $parsed = ZatcaSubmissionPayloadPersistence::parseSigningException($exception);
        $userMessage = $parsed['user_message'];
        $httpStatus = $parsed['http_status'];
        $requestId = $parsed['request_id'];
        $fieldErrors = $parsed['field_errors'];

        Log::warning('invoice.zatca_resubmit_failed', array_merge(
            SigningFailureSanitizer::logContext($exception),
            [
                'invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
                'http_status' => $httpStatus,
                'request_id' => $requestId,
            ],
        ));

        return $this->persistValidationFailure(
            $invoice,
            $userMessage,
            $fieldErrors,
            $httpStatus,
            $requestId,
            'api_error',
            $parsed['remote_message'],
        );
    }

    /**
     * @param  list<string>  $fieldErrors
     */
    private function persistValidationFailure(
        Invoice $invoice,
        string $summary,
        array $fieldErrors,
        ?int $httpStatus,
        ?string $requestId,
        string $code,
        ?string $remoteMessage = null,
    ): InvoiceZatcaResubmitResult {
        $flashMessage = ZatcaSubmissionPayloadPersistence::flashMessageForFailure($summary, $fieldErrors);

        $payload = ZatcaSubmissionPayloadPersistence::mergeAfterValidationFailure(
            $invoice->payload ?? [],
            $summary,
            $fieldErrors,
            $httpStatus,
            $requestId,
            $remoteMessage,
        );

        $this->persistIssuedInvoice($invoice, ['payload' => $payload]);

        $this->auditService->record($invoice->fresh(), 'invoice.zatca_resubmit_failed', [
            'message' => $summary,
            'http_status' => $httpStatus,
            'field_error_count' => count($fieldErrors),
        ]);

        return InvoiceZatcaResubmitResult::failure($code, $flashMessage, $invoice->fresh());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persistIssuedInvoice(Invoice $invoice, array $attributes): void
    {
        Invoice::withoutEvents(function () use ($invoice, $attributes): void {
            $invoice->forceFill($attributes)->save();
        });
    }
}