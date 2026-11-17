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
use App\Support\Invoicing\StandardInvoiceZatcaPayloadPrecheck;
use App\Support\Invoicing\ZHeartApiValidationErrors;
use App\Support\Invoicing\ZatcaResponseSanitizer;
use App\Support\Invoicing\ZatcaSubmissionOutcome;
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

            if (! $preserveChain) {
                $attributes = array_merge($attributes, [
                    'icv' => $signed['icv'],
                    'invoice_hash' => $signed['hash'],
                    'previous_invoice_hash' => $signed['previous_invoice_hash'],
                    'signed_xml' => $signed['cleared_xml'] ?? $signed['xml'],
                    'qr_tlv_base64' => $signed['qr'],
                ]);
            } elseif (blank($invoice->qr_tlv_base64) && filled($signed['qr'] ?? null)) {
                $attributes['qr_tlv_base64'] = $signed['qr'];
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

        $payload['signing_submission_status'] = ($signed['remote_submitted'] ?? false) ? 'submitted' : 'signed_locally';
        $payload['zheart_remote_submitted'] = (bool) ($signed['remote_submitted'] ?? false);
        unset($payload['zheart_last_error']);

        if (! empty($signed['request_id'])) {
            $payload['zheart_request_id'] = $signed['request_id'];
        }

        if (! empty($signed['remote_submission'])) {
            $sanitized = ZatcaResponseSanitizer::sanitizeForDisplay($signed['remote_submission']);
            $payload['zheart_zatca'] = $sanitized ?? $signed['remote_submission'];
            $payload['zheart_zatca_display'] = $sanitized;
        }

        if (filled($signed['reporting_status'] ?? null)) {
            $payload['zheart_reporting_status'] = $signed['reporting_status'];
        }

        if (filled($signed['zatca_delivery'] ?? null)) {
            $payload['zheart_zatca_delivery'] = $signed['zatca_delivery'];
        }

        if (filled($signed['zatca_http_status'] ?? null)) {
            $payload['zheart_zatca_http_status'] = $signed['zatca_http_status'];
        }

        $payload['zheart_zatca_submitted_at'] = now()->toIso8601String();

        if (! $preserveChain && filled($signed['invoice_uuid'] ?? null)) {
            $payload['invoice_uuid'] = $signed['invoice_uuid'];
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
        $userMessage = SigningFailureSanitizer::sanitizeMessage($exception->getMessage());
        $httpStatus = null;
        $requestId = null;
        $fieldErrors = [];
        $previous = $exception->getPrevious();
        if ($previous instanceof ZHeartApiException) {
            $httpStatus = $previous->httpStatus > 0 ? $previous->httpStatus : null;
            $requestId = $previous->requestId;
            $fieldErrors = ZHeartApiValidationErrors::collect($previous->responseJson);
            if ($httpStatus !== null && $httpStatus >= 400) {
                $userMessage = __('invoices.zatca_resubmit_http_failed', [
                    'status' => $httpStatus,
                    'reason' => $userMessage,
                ]);
            }
        }

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
            SigningFailureSanitizer::sanitizeMessage($exception->getMessage()),
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
        $fieldErrors = ZHeartApiValidationErrors::dedupe($fieldErrors);
        $flashMessage = ZHeartApiValidationErrors::formatFlashMessage($summary, $fieldErrors);

        $display = ZatcaResponseSanitizer::sanitizeForDisplay([
            'status' => 'error',
            'http_status' => $httpStatus,
            'request_id' => $requestId,
            'field_errors' => $fieldErrors,
            'message' => $remoteMessage ?? $summary,
        ]);

        $payload = array_merge($invoice->payload ?? [], [
            'zheart_last_error' => $summary,
            'zheart_last_error_at' => now()->toIso8601String(),
            'zheart_last_http_status' => $httpStatus,
            'zheart_field_errors' => $fieldErrors,
            'zheart_zatca_display' => $display,
            'zheart_zatca_submitted_at' => now()->toIso8601String(),
        ]);

        if ($requestId !== null) {
            $payload['zheart_request_id'] = $requestId;
        }

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