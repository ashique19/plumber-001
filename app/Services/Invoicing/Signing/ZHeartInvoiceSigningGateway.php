<?php

namespace App\Services\Invoicing\Signing;

use App\Contracts\Invoicing\InvoiceSigningGateway;
use App\Exceptions\InvoiceSigningException;
use App\Models\Invoice;
use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Services\Invoicing\ZHeart\ZHeartClient;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;
use App\Support\Invoicing\ZHeartApiUserMessage;

final class ZHeartInvoiceSigningGateway implements InvoiceSigningGateway
{
    public function __construct(
        private readonly ZHeartClient $client,
        private readonly ZHeartInvoicePayloadBuilder $payloadBuilder,
    ) {}

    public function signIssuedInvoice(InvoiceSigningRequest $request, Invoice $invoice): array
    {
        try {
            $payload = $this->payloadBuilder->build($request);
        } catch (\InvalidArgumentException $e) {
            throw new InvoiceSigningException($e->getMessage());
        }

        $autoSubmitSigned = config('zheart.auto_submit_signed') && $request->business->invoicingIntegrationEnabled();

        try {
            if ($autoSubmitSigned) {
                $result = $this->submitPayload($request, $payload);
            } else {
                $result = $this->client->sign($payload);
            }
        } catch (ZHeartApiException $e) {
            throw new InvoiceSigningException(ZHeartApiUserMessage::forException($e), 0, $e);
        }

        $data = $result['data'];
        $xml = $this->decodeSignedXml((string) ($data['signed_xml'] ?? ''));
        if ($xml === '') {
            throw InvoiceSigningException::signingFailed(context: 'Z-Heart response missing signed XML.');
        }

        $zatca = is_array($data['zatca'] ?? null)
            ? $data['zatca']
            : (is_array($data['submission'] ?? null) ? $data['submission'] : null);

        $reportingStatus = null;
        if (is_array($zatca) && isset($zatca['status'])) {
            $reportingStatus = (string) $zatca['status'];
        } elseif (isset($data['reporting_status'])) {
            $reportingStatus = (string) $data['reporting_status'];
        }

        return [
            'xml' => $xml,
            'hash' => (string) ($data['invoice_hash'] ?? ''),
            'qr' => isset($data['qr']) ? (string) $data['qr'] : null,
            'icv' => (int) ($data['icv'] ?? 0),
            'previous_invoice_hash' => (string) ($data['pih'] ?? ''),
            'invoice_uuid' => (string) ($data['uuid'] ?? $request->invoiceUuid),
            'remote_submitted' => $autoSubmitSigned,
            'remote_submission' => $zatca,
            'reporting_status' => $reportingStatus,
            'cleared_xml' => isset($data['cleared_xml']) ? $this->decodeSignedXml((string) $data['cleared_xml']) : null,
            'request_id' => $result['request_id'] ?? null,
        ];
    }

  /**
     * Re-submit an already issued document to ZATCA using the same UUID and invoice content (idempotent).
     *
     * @return array{
     *     xml?: string,
     *     hash?: string,
     *     qr?: ?string,
     *     icv?: int,
     *     previous_invoice_hash?: string,
     *     invoice_uuid?: string,
     *     remote_submitted?: bool,
     *     remote_submission?: array<string, mixed>|null,
     *     reporting_status?: string|null,
     *     cleared_xml?: string|null,
     *     request_id?: string|null,
     * }
     */
    public function submitInvoiceToZatca(InvoiceSigningRequest $request): array
    {
        if (! $request->business->invoicingIntegrationEnabled()) {
            throw new InvoiceSigningException(__('zheart_api.not_configured'));
        }

        try {
            $payload = $this->payloadBuilder->build($request);
        } catch (\InvalidArgumentException $e) {
            throw new InvoiceSigningException($e->getMessage());
        }

        try {
            $result = $this->submitPayload($request, $payload);
        } catch (ZHeartApiException $e) {
            throw new InvoiceSigningException(ZHeartApiUserMessage::forException($e), 0, $e);
        }

        return $this->mapZatcaSubmissionResponse($result, $request->invoiceUuid, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function submitPayload(InvoiceSigningRequest $request, array $payload): array
    {
        return $request->invoiceCategory === 'simplified'
            ? $this->client->report($payload)
            : $this->client->clear($payload);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mapZatcaSubmissionResponse(array $result, string $invoiceUuid, bool $remoteSubmitted): array
    {
        $data = $result['data'];

        $zatca = is_array($data['zatca'] ?? null)
            ? $data['zatca']
            : (is_array($data['submission'] ?? null) ? $data['submission'] : null);

        $reportingStatus = null;
        if (is_array($zatca) && isset($zatca['status'])) {
            $reportingStatus = (string) $zatca['status'];
        } elseif (isset($data['reporting_status'])) {
            $reportingStatus = (string) $data['reporting_status'];
        }

        $xml = isset($data['signed_xml']) ? $this->decodeSignedXml((string) $data['signed_xml']) : null;

        return [
            'xml' => $xml,
            'hash' => isset($data['invoice_hash']) ? (string) $data['invoice_hash'] : null,
            'qr' => isset($data['qr']) ? (string) $data['qr'] : null,
            'icv' => isset($data['icv']) ? (int) $data['icv'] : null,
            'previous_invoice_hash' => isset($data['pih']) ? (string) $data['pih'] : null,
            'invoice_uuid' => (string) ($data['uuid'] ?? $invoiceUuid),
            'remote_submitted' => $remoteSubmitted,
            'remote_submission' => $zatca,
            'reporting_status' => $reportingStatus,
            'cleared_xml' => isset($data['cleared_xml']) ? $this->decodeSignedXml((string) $data['cleared_xml']) : null,
            'request_id' => $result['request_id'] ?? null,
        ];
    }

    private function decodeSignedXml(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        if (str_starts_with($encoded, '<?xml') || str_starts_with($encoded, '<')) {
            return $encoded;
        }

        $decoded = base64_decode($encoded, true);

        return $decoded !== false ? $decoded : '';
    }
}
