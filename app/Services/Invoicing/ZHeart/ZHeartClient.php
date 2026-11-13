<?php

namespace App\Services\Invoicing\ZHeart;

use App\Support\Invoicing\ZHeartApiUserMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ZHeartClient
{
    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{data: array<string, mixed>, request_id: ?string}
     */
    public function sign(array $invoicePayload): array
    {
        return $this->invoiceCall('invoices/sign', $invoicePayload, (int) config('zheart.timeout_sign_seconds', 45));
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{data: array<string, mixed>, request_id: ?string}
     */
    public function report(array $invoicePayload): array
    {
        return $this->invoiceCall('invoices/report', $invoicePayload, (int) config('zheart.timeout_submit_seconds', 120));
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{data: array<string, mixed>, request_id: ?string}
     */
    public function clear(array $invoicePayload): array
    {
        return $this->invoiceCall('invoices/clear', $invoicePayload, (int) config('zheart.timeout_submit_seconds', 120));
    }

    public function health(): bool
    {
        $base = $this->baseUrl();
        if ($base === '') {
            return false;
        }

        try {
            $response = Http::timeout((int) config('zheart.timeout_health_seconds', 5))
                ->acceptJson()
                ->get($base.'/api/v1/health');

            return $response->successful() && ($response->json('status') === 'ok');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{data: array<string, mixed>, request_id: ?string}
     */
    private function invoiceCall(string $path, array $invoicePayload, int $timeoutSeconds): array
    {
        $base = $this->baseUrl();
        $apiKey = $this->resolveApiKey();
        if ($base === '' || $apiKey === null || $apiKey === '') {
            throw new ZHeartApiException(__('zheart_api.not_configured'), 0);
        }

        $url = $base.'/api/v1/'.$path;
        $body = ['invoice' => $invoicePayload];
        $attempts = 1 + max(0, (int) config('zheart.retry_times', 2));
        $sleepMs = max(0, (int) config('zheart.retry_sleep_ms', 500));

        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->acceptJson()
                    ->withToken($apiKey)
                    ->post($url, $body);

                $requestId = $response->header('X-Request-Id') ?: $response->json('request_id');
                $json = $response->json();

                if ($response->successful() && is_array($json) && ($json['status'] ?? '') === 'ok') {
                    $data = is_array($json['data'] ?? null) ? $json['data'] : [];

                    return [
                        'data' => $data,
                        'request_id' => is_string($requestId) ? $requestId : null,
                    ];
                }

                $status = $response->status();
                $message = ZHeartApiUserMessage::fromResponse($status, is_array($json) ? $json : null, is_string($requestId) ? $requestId : null);
                $errorCode = is_string(data_get($json, 'data.error')) ? (string) data_get($json, 'data.error') : null;

                if ($status >= 500 && $attempt < $attempts) {
                    usleep($sleepMs * 1000);

                    continue;
                }

                throw new ZHeartApiException($message, $status, is_string($requestId) ? $requestId : null, $errorCode);
            } catch (ConnectionException $e) {
                $lastException = $e;
                if ($attempt < $attempts) {
                    usleep($sleepMs * 1000);

                    continue;
                }
            } catch (RequestException $e) {
                if ($e->response !== null && $e->response->status() >= 500 && $attempt < $attempts) {
                    usleep($sleepMs * 1000);

                    continue;
                }

                throw $e;
            }
        }

        Log::warning('zheart.request_failed', [
            'path' => $path,
            'invoice_uuid' => $invoicePayload['uuid'] ?? null,
            'attempts' => $attempts,
        ]);

        throw new ZHeartApiException(__('zheart_api.server_error', ['id' => '—']), 503, null, null, $lastException);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('zheart.base_url', ''), '/');
    }

    private function resolveApiKey(): ?string
    {
        $fromEnv = config('zheart.api_key');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }
}
