<?php

namespace App\Services\Invoicing;

final class HubDraftIssueOutcome
{
    private function __construct(
        public readonly string $status,
        public readonly ?InvoiceIssueResult $result = null,
        public readonly ?string $message = null,
    ) {}

    public static function issued(InvoiceIssueResult $result): self
    {
        return new self('issued', $result);
    }

    public static function blocked(string $message): self
    {
        return new self('blocked', message: $message);
    }

    public static function failed(string $message): self
    {
        return new self('failed', message: $message);
    }

    public function ok(): bool
    {
        return $this->status === 'issued';
    }
}