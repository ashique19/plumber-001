<?php

namespace App\Services\Invoicing\Signing;

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use Illuminate\Support\Carbon;

final class InvoiceSigningRequest
{
    /**
     * @param  list<InvoiceLineData>  $lines
     * @param  array<string, mixed>  $invoicePayload
     */
    public function __construct(
        public readonly BusinessSetting $business,
        public readonly string $documentNumber,
        public readonly InvoiceDocumentType $documentType,
        public readonly string $invoiceCategory,
        public readonly array $lines,
        public readonly array $invoicePayload,
        public readonly Carbon $issueDateTimeUtc,
        public readonly string $invoiceUuid,
        public readonly ?string $referencedInvoiceNumber,
        public readonly ?string $referencedInvoiceUuid,
        public readonly ?string $noteReason,
    ) {}
}
