<?php

namespace App\Support\Invoicing;

use App\Services\Invoicing\ZHeart\ZHeartApiException;

final class ZHeartApiUserMessage
{
    public static function fromResponse(int $status, ?array $json, ?string $requestId): string
    {
        $id = $requestId ?? '—';

        return match ($status) {
            401 => __('zheart_api.auth_failed', ['id' => $id]),
            403 => __('zheart_api.ip_not_allowed', ['id' => $id]),
            402, 423 => __('zheart_api.client_suspended', ['id' => $id]),
            422 => __('zheart_api.validation_failed', ['id' => $id]),
            default => __('zheart_api.server_error', ['id' => $id]),
        };
    }

    public static function forException(ZHeartApiException $e): string
    {
        return $e->getMessage();
    }
}
