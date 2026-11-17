<?php

namespace App\Support\Invoicing;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Invoicing\Signing\InvoiceSigningRequestFactory;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;

/**
 * Builds the JSON body sent to Z-Heart for an issued invoice (issue clearance and resubmit).
 */
final class ZHeartInvoicePayloadFromInvoice
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Invoice $invoice, BusinessSetting $business): array
    {
        $request = InvoiceSigningRequestFactory::fromIssuedInvoice($invoice, $business);

        return app(ZHeartInvoicePayloadBuilder::class)->build($request);
    }
}