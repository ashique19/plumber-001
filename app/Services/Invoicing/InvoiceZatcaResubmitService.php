<?php

namespace App\Services\Invoicing;

use App\Business\BusinessContext;
use App\Exceptions\InvoiceSigningException;
use App\Models\Invoice;
use App\Services\InvoiceAuditService;
use App\Services\Invoicing\Signing\InvoiceSigningRequestFactory;
use App\Services\Invoicing\Signing\ZHeartInvoiceSigningGateway;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceZatcaTab;
use App\Support\Invoicing\SigningFailureSanitizer;
use App\Support\Invoicing\InvoiceZatcaSubmissionGuard;
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
                return InvoiceZatcaResubmitResult::locked();
            }

            $business = BusinessContext::require();
            $request = InvoiceSigningRequestFactory::fromIssuedInvoice($invoice, $business);

            $preserveChain = filled($invoice->icv) && filled($invoice->invoice_hash);

            InvoiceZatcaSubmissionGuard::markSubmissionInFlight($invoice);

            try {
                if ($preserveChain && filled($invoice->signed_xml)) {
                    $signed = $this->gateway->submitInvoiceToZatca($request);
                } else {
                    if ($preserveChain) {
                        return InvoiceZatcaResubmitResult::notEligible();
                    }
                    $signed = $this->gateway->signIssuedInvoice($request, $invoice);
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

        $payload['zheart_zatca_submitted_at'] = now()->toIso8601String();

        if (! $preserveChain && filled($signed['invoice_uuid'] ?? null)) {
            $payload['invoice_uuid'] = $signed['invoice_uuid'];
        }

        return $payload;
    }

    private function recordFailure(Invoice $invoice, InvoiceSigningException $exception): InvoiceZatcaResubmitResult
    {
        $userMessage = SigningFailureSanitizer::sanitizeMessage($exception->getMessage());

        Log::warning('invoice.zatca_resubmit_failed', array_merge(
            SigningFailureSanitizer::logContext($exception),
            ['invoice_id' => $invoice->id],
        ));

        $payload = array_merge($invoice->payload ?? [], [
            'zheart_last_error' => $userMessage,
            'zheart_last_error_at' => now()->toIso8601String(),
        ]);

        $this->persistIssuedInvoice($invoice, ['payload' => $payload]);

        $this->auditService->record($invoice->fresh(), 'invoice.zatca_resubmit_failed', [
            'message' => $userMessage,
        ]);

        return InvoiceZatcaResubmitResult::failure('api_error', $userMessage, $invoice->fresh());
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