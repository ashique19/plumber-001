<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;

final class InvoiceIssueResult
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly bool $signingSucceeded,
        public readonly ?string $signingFailureMessage = null,
    ) {}
}