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
        public readonly string $flashLevel = 'success',
    ) {}

    public static function success(Invoice $invoice): self
    {
        return new self(true, 'success', $invoice);
    }

    public static function fromSubmissionOutcome(Invoice $invoice, \App\Support\Invoicing\ZatcaSubmissionOutcome $outcome): self
    {
        return new self(
            $outcome->ok,
            $outcome->code,
            $invoice,
            $outcome->userMessage,
            $outcome->flashLevel,
        );
    }

    public static function failure(string $code, string $userMessage, ?Invoice $invoice = null): self
    {
        return new self(false, $code, $invoice, $userMessage, 'error');
    }

    public static function locked(): self
    {
        return new self(false, 'locked', null, __('invoices.zatca_resubmit_locked'), 'warning');
    }

    public static function alreadySuccess(): self
    {
        return new self(false, 'already_success', null, __('invoices.zatca_resubmit_already_success'), 'info');
    }

    public static function notEligible(): self
    {
        return new self(false, 'not_eligible', null, __('invoices.zatca_resubmit_not_eligible'), 'error');
    }
}