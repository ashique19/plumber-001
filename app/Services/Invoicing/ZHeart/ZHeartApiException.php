<?php

namespace App\Services\Invoicing\ZHeart;

use App\Support\Invoicing\ZHeartSubmissionOutcome;

use RuntimeException;

class ZHeartApiException extends RuntimeException
{
    public function __construct(
        string $userMessage,
        public readonly int $httpStatus,
        public readonly ?string $requestId = null,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
        public readonly ?array $responseJson = null,
    ) {
        parent::__construct($userMessage, $httpStatus, $previous);
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function outcome(): ?string
    {
        return ZHeartSubmissionOutcome::normalize(data_get($this->responseJson, 'data.outcome'));
    }

    public function isRetryable(): bool
    {
        return ZHeartSubmissionOutcome::isRetryable(
            $this->outcome(),
            data_get($this->responseJson, 'data.retryable'),
        );
    }

    public function invoiceNumber(): ?string
    {
        $number = data_get($this->responseJson, 'data.invoice_number');

        return is_string($number) && $number !== '' ? $number : null;
    }
}
