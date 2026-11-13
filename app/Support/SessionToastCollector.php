<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Maps Laravel session flash keys into toast payloads for the global toast stack.
 */
final class SessionToastCollector
{
    /**
     * @return list<array{type: string, message: string, title: ?string, persistent: bool}>
     */
    public static function fromRequest(Request $request): array
    {
        $toasts = [];

        $map = [
            'success' => 'success',
            'status' => 'success',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'error',
        ];

        foreach ($map as $sessionKey => $type) {
            $message = $request->session()->get($sessionKey);
            if (is_string($message) && $message !== '') {
                $toasts[] = [
                    'type' => $type,
                    'message' => $message,
                    'title' => null,
                    'persistent' => $type === 'error',
                ];
            }
        }

        return $toasts;
    }
}
