<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceAuditLog;
class InvoiceAuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(Invoice $invoice, string $event, array $metadata = []): InvoiceAuditLog
    {
        $redacted = $this->redactSecrets($metadata);

        return InvoiceAuditLog::query()->create([
            'invoice_id' => $invoice->id,
            'event' => $event,
            'context' => $redacted,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function redactSecrets(array $metadata): array
    {
        $keys = ['private_key', 'certificate', 'encrypted_private_key', 'api_key'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $metadata)) {
                $metadata[$key] = '[redacted]';
            }
        }

        return $metadata;
    }
}
