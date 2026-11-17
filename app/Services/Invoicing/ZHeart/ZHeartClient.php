<?php

namespace App\Services\Invoicing\ZHeart;

use App\Models\BusinessSetting;
use App\Support\Invoicing\ZHeartApiUserMessage;
use App\Support\Invoicing\ZHeartConnectionTestReport;
use App\Support\Invoicing\ZHeartCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use App\Business\BusinessContext;
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
     * @return array{
     *     ok: bool,
     *     health_ok: bool,
     *     auth_ok: bool,
     *     message: string,
     *     http_status: ?int,
     *     severity: 'ok'|'error'|'warn',
     *     reason: ?string,
     *     card: ?array<string, mixed>,
     *     warnings: list<string>
     * }
     */
    public function testConnection(?string $baseUrl = null, ?string $apiKey = null, ?BusinessSetting $business = null): array
    {
        $base = rtrim($baseUrl ?? $this->baseUrl(), '/');
        $apiKey ??= $this->resolveApiKey();
        $business ??= BusinessContext::get();

        if ($base === '' || $apiKey === null || $apiKey === '') {
            return [
                'ok' => false,
                'health_ok' => false,
                'auth_ok' => false,
                'message' => __('business.zheart.test_reason_not_configured'),
                'http_status' => null,
                'severity' => 'error',
                'reason' => 'not_configured',
                'card' => null,
                'warnings' => [],
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
            $report = ZHeartConnectionTestReport::successFromAuthPayload($authBody, $business);

            return [
                'ok' => true,
                'health_ok' => $healthOk,
                'auth_ok' => true,
                'message' => $report['message'],
                'http_status' => 200,
                'severity' => 'ok',
                'reason' => null,
                'card' => $report['card'],
                'warnings' => $report['warnings'],
            ];
        }

        if ($authStatus === 404) {
            $failure = ZHeartConnectionTestReport::failureReason(404, $healthOk, $authBody);

            return [
                'ok' => false,
                'health_ok' => $healthOk,
                'auth_ok' => false,
                'message' => $failure['message'],
                'http_status' => 404,
                'severity' => 'warn',
                'reason' => $failure['reason'],
                'card' => null,
                'warnings' => [],
            ];
        }

        if (in_array($authStatus, [401, 403], true)) {
            $failure = ZHeartConnectionTestReport::failureReason($authStatus, $healthOk, $authBody);

            return [
                'ok' => false,
                'health_ok' => $healthOk,
                'auth_ok' => false,
                'message' => $failure['message'],
                'http_status' => $authStatus,
                'severity' => 'error',
                'reason' => $failure['reason'],
                'card' => null,
                'warnings' => [],
            ];
        }

        $failure = ZHeartConnectionTestReport::failureReason($authStatus, $healthOk, $authBody);

        return [
            'ok' => false,
            'health_ok' => $healthOk,
            'auth_ok' => false,
            'message' => $failure['message'],
            'http_status' => $authStatus,
            'severity' => $failure['reason'] === 'outdated' ? 'warn' : 'error',
            'reason' => $failure['reason'],
            'card' => null,
            'warnings' => [],
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

                    if ($submitPath) {
                        Log::info('zheart.invoice_submit', [
                            'path' => $path,
                            'invoice_uuid' => $invoicePayload['uuid'] ?? null,
                            'request_id' => is_string($requestId) ? $requestId : null,
                            'reporting_status' => is_string($data['reporting_status'] ?? null)
                                ? $data['reporting_status']
                                : (is_array($data['zatca'] ?? null) ? ($data['zatca']['status'] ?? null) : null),
                        ]);
                    }

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
