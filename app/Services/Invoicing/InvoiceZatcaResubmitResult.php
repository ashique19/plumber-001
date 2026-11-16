<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;

final class InvoiceZatcaResubmitResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly ?Invoice $invoice = null,
        public readonly ?string $userMessage = null,
    ) {}

    public static function success(Invoice $invoice): self
    {
        return new self(true, 'success', $invoice);
    }

    public static function failure(string $code, string $userMessage, ?Invoice $invoice = null): self
    {
        return new self(false, $code, $invoice, $userMessage);
    }

    public static function locked(): self
    {
        return new self(false, 'locked', null, __('invoices.zatca_resubmit_locked'));
    }

    public static function alreadySuccess(): self
    {
        return new self(false, 'already_success', null, __('invoices.zatca_resubmit_already_success'));
    }

    public static function notEligible(): self
    {
        return new self(false, 'not_eligible', null, __('invoices.zatca_resubmit_not_eligible'));
    }
}