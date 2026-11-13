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
        $payload = $this->payloadBuilder->build($request);
        $autoSubmitSigned = config('zheart.auto_submit_signed') && $request->business->invoicingIntegrationEnabled();

        try {
            if ($autoSubmitSigned) {
                $result = $request->invoiceCategory === 'simplified'
                    ? $this->client->report($payload)
                    : $this->client->clear($payload);
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

        return [
            'xml' => $xml,
            'hash' => (string) ($data['invoice_hash'] ?? ''),
            'qr' => isset($data['qr']) ? (string) $data['qr'] : null,
            'icv' => (int) ($data['icv'] ?? 0),
            'previous_invoice_hash' => (string) ($data['pih'] ?? ''),
            'invoice_uuid' => (string) ($data['uuid'] ?? $request->invoiceUuid),
            'remote_submitted' => $autoSubmitSigned,
            'remote_submission' => is_array($data['submission'] ?? null) ? $data['submission'] : null,
            'reporting_status' => isset($data['reporting_status']) ? (string) $data['reporting_status'] : null,
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
