<?php

namespace App\Services\Invoicing\ZHeart;

use App\Support\Invoicing\ZHeartApiUserMessage;
use App\Support\Invoicing\ZHeartCredentials;
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
        return $this->testConnection()['ok'];
    }

    /**
     * @return array{ok: bool, health_ok: bool, auth_ok: bool, message: string, http_status: ?int, severity: 'ok'|'error'|'warn'}
     */
    public function testConnection(?string $baseUrl = null, ?string $apiKey = null): array
    {
        $base = rtrim($baseUrl ?? $this->baseUrl(), '/');
        $apiKey ??= $this->resolveApiKey();

        if ($base === '' || $apiKey === null || $apiKey === '') {
            return [
                'ok' => false,
                'health_ok' => false,
                'auth_ok' => false,
                'message' => __('business.zheart.test_not_configured'),
                'http_status' => null,
                'severity' => 'error',
            ];
        }

        $timeout = (int) config('zheart.timeout_health_seconds', 5);
        $healthOk = false;

        try {
            $health = Http::timeout($timeout)
                ->acceptJson()
                ->get($base.'/api/v1/health');
            $healthOk = $health->successful() && ($health->json('status') === 'ok');
        } catch (\Throwable) {
            $healthOk = false;
        }

        $authStatus = null;
        /** @var array<string, mixed>|null $authBody */
        $authBody = null;

        try {
            $auth = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($apiKey)
                ->get($base.'/api/v1/auth');
            $authStatus = $auth->status();
            $payload = $auth->json();
            $authBody = is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            $authStatus = null;
        }

        if ($authStatus === 200 && is_array($authBody) && ($authBody['status'] ?? '') === 'ok') {
            $client = is_array($authBody['client'] ?? null) ? $authBody['client'] : [];
            $onboarded = $client['onboarded'] ?? null;
            $onboardedLabel = match (true) {
                $onboarded === true => __('business.zheart.onboarded_yes'),
                $onboarded === false => __('business.zheart.onboarded_no'),
                default => __('business.zheart.onboarded_unknown'),
            };

            return [
                'ok' => true,
                'health_ok' => $healthOk,
                'auth_ok' => true,
                'message' => __('business.zheart.test_success_client', [
                    'name' => (string) ($client['name'] ?? '—'),
                    'environment' => (string) ($client['environment'] ?? '—'),
                    'onboarded' => $onboardedLabel,
                ]),
                'http_status' => 200,
                'severity' => 'ok',
            ];
        }

        if ($authStatus === 404) {
            return [
                'ok' => false,
                'health_ok' => $healthOk,
                'auth_ok' => false,
                'message' => __('business.zheart.test_auth_endpoint_missing'),
                'http_status' => 404,
                'severity' => 'warn',
            ];
        }

        if (in_array($authStatus, [401, 403], true)) {
            return [
                'ok' => false,
                'health_ok' => $healthOk,
                'auth_ok' => false,
                'message' => __('business.zheart.test_auth_failed_allowlist'),
                'http_status' => $authStatus,
                'severity' => 'error',
            ];
        }

        if ($healthOk && $authStatus !== null) {
            return [
                'ok' => false,
                'health_ok' => true,
                'auth_ok' => false,
                'message' => __('business.zheart.test_auth_failed'),
                'http_status' => $authStatus,
                'severity' => 'error',
            ];
        }

        return [
            'ok' => false,
            'health_ok' => $healthOk,
            'auth_ok' => false,
            'message' => __('business.zheart.test_unreachable'),
            'http_status' => $authStatus,
            'severity' => 'error',
        ];
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
        $submitPath = str_contains($path, 'report') || str_contains($path, 'clear');
        $attempts = $submitPath
            ? 1
            : 1 + max(0, (int) config('zheart.retry_times', 2));
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

                if (! $submitPath && $status >= 500 && $attempt < $attempts) {
                    usleep($sleepMs * 1000);

                    continue;
                }

                throw new ZHeartApiException($message, $status, is_string($requestId) ? $requestId : null, $errorCode);
            } catch (ConnectionException $e) {
                $lastException = $e;
                if (! $submitPath && $attempt < $attempts) {
                    usleep($sleepMs * 1000);

                    continue;
                }
            } catch (RequestException $e) {
                if (
                    ! $submitPath
                    && $e->response !== null
                    && $e->response->status() >= 500
                    && $attempt < $attempts
                ) {
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
        return ZHeartCredentials::baseUrl();
    }

    private function resolveApiKey(): ?string
    {
        return ZHeartCredentials::apiKey();
    }
}
