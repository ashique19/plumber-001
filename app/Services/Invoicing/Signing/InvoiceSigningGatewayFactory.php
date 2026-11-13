<?php

namespace App\Services\Invoicing\Signing;

use App\Contracts\Invoicing\InvoiceSigningGateway;

final class InvoiceSigningGatewayFactory
{
    public function __construct(
        private readonly ZHeartInvoiceSigningGateway $zheart,
    ) {}

    public function gateway(): InvoiceSigningGateway
    {
        return $this->zheart;
    }
}
