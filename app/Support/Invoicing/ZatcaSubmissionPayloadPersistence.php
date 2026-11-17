<?php

namespace App\Support\Invoicing;

use App\Exceptions\InvoiceSigningException;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Support\Hub\HubInvoiceZatcaTab;

/**
 * Shared payload keys after Z-Heart submit/sign on issue and resubmit.
 */
final class ZatcaSubmissionPayloadPersistence
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $signed
     * @return array<string, mixed>
     */
    public static function mergeAfterRemoteSubmission(array $payload, array $signed, bool $unsetLastError = true): array
    {
        $payload['signing_submission_status'] = ($signed['remote_submitted'] ?? false) ? 'submitted' : 'signed_locally';
        $payload['zheart_remote_submitted'] = (bool) ($signed['remote_submitted'] ?? false);

        if ($unsetLastError) {
            unset($payload['zheart_last_error']);
        }

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

        if (filled($signed['invoice_uuid'] ?? null)) {
            $payload['invoice_uuid'] = $signed['invoice_uuid'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $fieldErrors
     * @return array<string, mixed>
     */
    public static function mergeAfterValidationFailure(
        array $payload,
        string $summary,
        array $fieldErrors,
        ?int $httpStatus,
        ?string $requestId,
        ?string $remoteMessage = null,
    ): array {
        $fieldErrors = ZHeartApiValidationErrors::dedupe($fieldErrors);

        $display = ZatcaResponseSanitizer::sanitizeForDisplay([
            'status' => 'error',
            'http_status' => $httpStatus,
            'request_id' => $requestId,
            'field_errors' => $fieldErrors,
            'message' => $remoteMessage ?? $summary,
        ]);

        $payload['signing_submission_status'] = 'not_submitted';
        $payload['zheart_remote_submitted'] = false;
        $payload['zheart_reporting_status'] = 'pending';
        $payload['zheart_last_error'] = $summary;
        $payload['zheart_last_error_at'] = now()->toIso8601String();
        $payload['zheart_last_http_status'] = $httpStatus;
        $payload['zheart_field_errors'] = $fieldErrors;
        $payload['zheart_zatca_display'] = $display;
        $payload['zheart_zatca_submitted_at'] = now()->toIso8601String();

        if ($requestId !== null) {
            $payload['zheart_request_id'] = $requestId;
        }

        return $payload;
    }

    /**
     * @return array{
     *     user_message: string,
     *     field_errors: list<string>,
     *     http_status: ?int,
     *     request_id: ?string,
     *     remote_message: string,
     * }
     */
    public static function parseSigningException(InvoiceSigningException $exception): array
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

        return [
            'user_message' => $userMessage,
            'field_errors' => $fieldErrors,
            'http_status' => $httpStatus,
            'request_id' => $requestId,
            'remote_message' => SigningFailureSanitizer::sanitizeMessage($exception->getMessage()),
        ];
    }

    public static function flashMessageForFailure(string $summary, array $fieldErrors): string
    {
        return ZHeartApiValidationErrors::formatFlashMessage($summary, $fieldErrors);
    }

    public static function remoteSigningSucceeded(BusinessSetting $business, Invoice $issued): bool
    {
        if (! $business->invoicingIntegrationEnabled()) {
            return true;
        }

        if (! config('zheart.auto_submit_signed')) {
            return true;
        }

        if (data_get($issued->payload, 'zheart_remote_submitted') !== true) {
            return false;
        }

        if (HubInvoiceZatcaTab::isSuccess($issued)) {
            return true;
        }

        if (filled(data_get($issued->payload, 'zheart_last_error'))) {
            return false;
        }

        $effective = strtolower((string) \App\Support\Invoicing\InvoiceZatcaSubmissionGuard::effectiveReportingStatus($issued));

        return ! in_array($effective, HubInvoiceZatcaTab::failedReportingStatuses(), true)
            && $effective !== 'pending';
    }
}