<?php

namespace App\Contracts\Invoicing;

use App\Models\Invoice;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;

interface InvoiceSigningGateway
{
    /**
     * @return array{
     *     xml: string,
     *     hash: string,
     *     qr: ?string,
     *     icv: int,
     *     previous_invoice_hash: string,
     *     invoice_uuid: string,
     *     remote_submitted?: bool,
     *     remote_submission?: array<string, mixed>|null,
     *     reporting_status?: string|null,
     *     cleared_xml?: string|null,
     *     request_id?: string|null,
     * }
     */
    public function signIssuedInvoice(InvoiceSigningRequest $request, Invoice $invoice): array;
}
