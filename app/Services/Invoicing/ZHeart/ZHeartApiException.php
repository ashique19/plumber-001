<?php

namespace App\Services\Invoicing\ZHeart;

use RuntimeException;

class ZHeartApiException extends RuntimeException
{
    public function __construct(
        string $userMessage,
        public readonly int $httpStatus,
        public readonly ?string $requestId = null,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($userMessage, $httpStatus, $previous);
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
