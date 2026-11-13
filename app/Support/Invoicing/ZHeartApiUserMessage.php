<?php

namespace App\Support\Invoicing;

use App\Services\Invoicing\ZHeart\ZHeartApiException;

final class ZHeartApiUserMessage
{
    public static function fromResponse(int $status, ?array $json, ?string $requestId): string
    {
        $id = $requestId ?? '—';
        $remoteMessage = is_string($json['message'] ?? null) ? trim((string) $json['message']) : '';
        $errorCode = is_string(data_get($json, 'data.error')) ? (string) data_get($json, 'data.error') : null;

        return match ($status) {
            401 => self::joinMessage($remoteMessage, $errorCode)
                ?: __('zheart_api.auth_failed', ['id' => $id]),
            403 => match ($errorCode) {
                'client_suspended' => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.client_suspended', ['id' => $id]),
                'client_not_active' => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.client_not_active', ['id' => $id]),
                'ip_not_allowed' => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.ip_not_allowed', ['id' => $id]),
                default => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.ip_not_allowed', ['id' => $id]),
            },
            402, 423 => self::joinMessage($remoteMessage, $errorCode)
                ?: __('zheart_api.client_suspended', ['id' => $id]),
            422 => match ($errorCode) {
                'csid_required', 'egs_csid_required' => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.csid_required', ['id' => $id]),
                default => self::joinMessage($remoteMessage, $errorCode)
                    ?: __('zheart_api.validation_failed', ['id' => $id]),
            },
            default => self::joinMessage($remoteMessage, $errorCode)
                ?: __('zheart_api.server_error', ['id' => $id]),
        };
    }

    public static function forException(ZHeartApiException $e): string
    {
        return $e->getMessage();
    }

    private static function joinMessage(string $message, ?string $errorCode): string
    {
        if ($message === '' && ($errorCode === null || $errorCode === '')) {
            return '';
        }

        if ($message === '') {
            return $errorCode ?? '';
        }

        if ($errorCode === null || $errorCode === '' || str_contains($message, $errorCode)) {
            return $message;
        }

        return $message.' ('.$errorCode.')';
    }
}
