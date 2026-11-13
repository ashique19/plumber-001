<?php

namespace App\Exceptions;

use App\Support\Invoicing\SigningFailureSanitizer;

class InvoiceSigningException extends \RuntimeException
{
    public static function signingFailed(?\Throwable $previous = null, ?string $context = null): self
    {
        return new self(
            SigningFailureSanitizer::userMessage(__('invoices.errors.signing_failed'), $previous, $context),
            0,
            $previous
        );
    }

    public static function credentialsInvalid(?\Throwable $previous = null, ?string $context = null): self
    {
        return new self(
            SigningFailureSanitizer::userMessage(__('invoices.errors.signing_credentials_invalid'), $previous, $context),
            0,
            $previous
        );
    }
}
