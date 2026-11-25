<?php

namespace App\Support\Invoicing;

use App\Models\BusinessSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ZHeartConnectionTestReport
{
    /**
     * @param  array<string, mixed>  $authBody
     * @return array{message: string, card: array<string, mixed>, warnings: list<string>, ready: ?bool, issues: list<string>}
     */
    public static function successFromAuthPayload(array $authBody, ?BusinessSetting $business = null): array
    {
        $client = is_array($authBody['client'] ?? null) ? $authBody['client'] : [];
        $keyBlock = is_array($authBody['api_key'] ?? null)
            ? $authBody['api_key']
            : (is_array($authBody['key'] ?? null) ? $authBody['key'] : []);

        $vat = self::displayScalar(self::firstScalar($client, ['vat_number', 'vat_no', 'vat']));
        $cr = self::displayScalar(self::firstScalar($client, ['cr_number', 'cr_no', 'commercial_registration', 'cr']));
        $environment = strtolower((string) self::firstScalar($client, ['environment', 'env'], ''));
        $environmentLabel = $environment !== '' ? strtoupper($environment) : '—';
        $isProduction = in_array($environment, ['production', 'prod', 'live'], true);

        $activeRaw = self::firstScalar($client, ['active', 'is_active', 'client_active'], null);
        $activeLabel = self::formatBoolStatus($activeRaw, 'active_yes', 'active_no');

        $onboardedRaw = self::firstScalar($client, ['onboarded', 'onboarding_complete', 'onboarding_status'], null);
        $onboardingLabel = match (true) {
            $onboardedRaw === true || $onboardedRaw === 1 || $onboardedRaw === '1' || $onboardedRaw === 'true' => __('business.zheart.onboarded_yes'),
            $onboardedRaw === false || $onboardedRaw === 0 || $onboardedRaw === '0' || $onboardedRaw === 'false' => __('business.zheart.onboarded_no'),
            is_string($onboardedRaw) && $onboardedRaw !== '' => $onboardedRaw,
            default => '—',
        };

        $csidExpiresRaw = self::firstScalar($client, ['csid_expires_at', 'csid_expiry', 'csid_expires'], null)
            ?? self::firstScalar($authBody, ['csid_expires_at', 'csid_expiry'], null);
        [$csidExpiresLabel, $csidExpiresSoon] = self::formatExpiry($csidExpiresRaw);

        $lastZatca = self::formatDateTime(self::firstScalar($client, [
            'last_zatca_call_at',
            'last_zatca_at',
            'last_zatca_call',
            'zatca_last_call_at',
        ], null) ?? self::firstScalar($authBody, ['last_zatca_call_at'], null));

        $keyLabel = self::displayScalar(self::firstScalar($keyBlock, ['label', 'name', 'title']));
        $keyLast4 = self::displayScalar(self::firstScalar($keyBlock, ['last4', 'last_four', 'suffix']));
        if ($keyLast4 !== '—' && ! str_starts_with($keyLast4, '…')) {
            $keyLast4 = '…'.$keyLast4;
        }

        $keyExpiresRaw = self::firstScalar($keyBlock, ['expires_at', 'expiry', 'expires'], null);
        [$keyExpiresLabel] = self::formatExpiry($keyExpiresRaw, warnDays: null);

        $allowRaw = self::firstScalar($authBody, ['ip_allowlist_enabled'], null)
            ?? self::firstScalar($keyBlock, ['ip_allowlist_enabled', 'ip_allowlist'], null)
            ?? self::firstScalar($client, ['ip_allowlist_enabled'], null);
        $allowlistLabel = self::formatAllowlist($allowRaw);

        $card = [
            'client_name' => self::displayScalar(self::firstScalar($client, ['name', 'client_name', 'display_name'])),
            'vat_number' => $vat,
            'cr_number' => $cr,
            'environment' => $environmentLabel,
            'environment_production' => $isProduction,
            'active' => $activeLabel,
            'onboarding' => $onboardingLabel,
            'csid_expires' => $csidExpiresLabel,
            'csid_expires_soon' => $csidExpiresSoon,
            'last_zatca_call' => $lastZatca,
            'key_label' => $keyLabel,
            'key_last4' => $keyLast4,
            'key_expires' => $keyExpiresLabel,
            'ip_allowlist' => $allowlistLabel,
        ];

        $warnings = [];
        if ($csidExpiresSoon) {
            $warnings[] = __('business.zheart.test_warn_csid_expiring');
        }

        $businessVat = trim((string) ($business !== null ? data_get($business->settings, 'legal.vat_number', '') : ''));
        $remoteVat = self::normalizeVat(self::firstScalar($client, ['vat_number', 'vat_no', 'vat'], '') ?? '');
        $localVat = self::normalizeVat($businessVat);
        if ($remoteVat !== '' && $localVat !== '' && $remoteVat !== $localVat) {
            $warnings[] = __('business.zheart.test_warn_vat_mismatch', [
                'local' => $businessVat !== '' ? $businessVat : $localVat,
                'remote' => $vat !== '—' ? $vat : $remoteVat,
            ]);
        }

        $message = __('business.zheart.test_success_client', [
            'name' => $card['client_name'],
            'environment' => $card['environment'],
            'onboarded' => $onboardingLabel,
        ]);

        // Older Z-Heart versions do not send readiness; treat that as unknown (null), not as a problem.
        $ready = array_key_exists('ready', $authBody) && is_bool($authBody['ready']) ? $authBody['ready'] : null;
        $issues = $ready === false
            ? self::issueMessages($authBody['issues'] ?? [], $environment)
            : [];

        if ($ready === false) {
            $message = __('business.zheart.test_not_ready');
        }

        return [
            'message' => $message,
            'card' => $card,
            'warnings' => $warnings,
            'ready' => $ready,
            'issues' => $issues,
        ];
    }

    /**
     * Z-Heart readiness issues as plain sentences in the current language.
     * Known fields use our own translation; anything else shows Z-Heart's message.
     *
     * @return list<string>
     */
    public static function issueMessages(mixed $issues, string $environment = ''): array
    {
        if (! is_array($issues)) {
            return [];
        }

        $envLabel = $environment !== '' ? ucfirst(strtolower($environment)) : '—';
        $out = [];

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $field = is_string($issue['field'] ?? null) ? trim($issue['field']) : '';
            $remote = is_string($issue['message'] ?? null) ? trim($issue['message']) : '';

            $key = 'business.zheart.issues.'.$field;
            $local = ($field !== '' && preg_match('/^[a-z0-9_.]+$/', $field) && is_string(__($key)) && __($key) !== $key)
                ? __($key, ['environment' => $envLabel])
                : null;

            $text = $local ?? $remote;
            if ($text !== '' && ! in_array($text, $out, true)) {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $authBody
     */
    public static function failureReason(
        ?int $authStatus,
        bool $healthOk,
        ?array $authBody = null,
        ?string $requestId = null,
    ): array {
        $requestId ??= is_string(data_get($authBody, 'request_id')) ? (string) data_get($authBody, 'request_id') : null;
        $id = $requestId !== null && $requestId !== '' ? $requestId : '—';
        $remote = is_string(data_get($authBody, 'message')) ? trim((string) data_get($authBody, 'message')) : '';
        $errorCode = is_string(data_get($authBody, 'data.error')) ? (string) data_get($authBody, 'data.error') : '';

        if ($authStatus === 404) {
            return ['reason' => 'outdated', 'message' => __('business.zheart.test_reason_outdated')];
        }

        // 401 / 403: the key or the account was refused. Only these count as authentication problems.
        if ($authStatus === 401) {
            return match (true) {
                str_contains(strtolower($remote), 'revoked') => ['reason' => 'key_revoked', 'message' => __('business.zheart.test_reason_key_revoked')],
                str_contains(strtolower($remote), 'expired') => ['reason' => 'key_expired', 'message' => __('business.zheart.test_reason_key_expired')],
                default => ['reason' => 'bad_key', 'message' => __('business.zheart.test_reason_bad_key')],
            };
        }

        if ($authStatus === 403) {
            return match ($errorCode !== '' ? $errorCode : $remote) {
                'client_suspended' => ['reason' => 'client_suspended', 'message' => __('business.zheart.test_reason_client_suspended')],
                'client_not_active', 'Client is not active.' => ['reason' => 'client_not_active', 'message' => __('business.zheart.test_reason_client_not_active')],
                default => ['reason' => 'ip_not_allowed', 'message' => __('business.zheart.test_reason_ip_not_allowed')],
            };
        }

        // 5xx: a fault inside Z-Heart, never an authentication problem.
        if ($authStatus !== null && $authStatus >= 500) {
            return [
                'reason' => 'server_error',
                'message' => $id !== '—'
                    ? __('business.zheart.test_reason_server_error', ['id' => $id])
                    : __('business.zheart.test_reason_server_error_no_id'),
            ];
        }

        if ($authStatus === null) {
            return ['reason' => 'unreachable', 'message' => __('business.zheart.test_reason_unreachable')];
        }

        // 2xx that is not the expected JSON (e.g. an HTML error page or a proxy reply).
        if ($authStatus >= 200 && $authStatus < 300) {
            return [
                'reason' => 'bad_response',
                'message' => __('business.zheart.test_reason_bad_response', ['status' => $authStatus, 'id' => $id]),
            ];
        }

        return [
            'reason' => 'unexpected_status',
            'message' => __('business.zheart.test_reason_unexpected_status', ['status' => $authStatus, 'id' => $id]),
        ];
    }

    private static function normalizeVat(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits;
    }

    /**
     * @param  list<string>  $keys
     */
    private static function firstScalar(array $data, array $keys, mixed $default = '—'): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return $default;
    }

    private static function displayScalar(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private static function formatBoolStatus(mixed $value, string $yesKey, string $noKey): string
    {
        if ($value === null || $value === '—') {
            return '—';
        }

        $truthy = $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes';
        $falsy = $value === false || $value === 0 || $value === '0' || $value === 'false' || $value === 'no';

        if ($truthy) {
            return __('business.zheart.'.$yesKey);
        }
        if ($falsy) {
            return __('business.zheart.'.$noKey);
        }

        return '—';
    }

    private static function formatAllowlist(mixed $value): string
    {
        if ($value === null || $value === '—') {
            return '—';
        }

        if (is_bool($value)) {
            return $value
                ? __('business.zheart.allowlist_on')
                : __('business.zheart.allowlist_off');
        }

        if (is_string($value) && in_array(strtolower($value), ['on', 'enabled', 'true', '1'], true)) {
            return __('business.zheart.allowlist_on');
        }
        if (is_string($value) && in_array(strtolower($value), ['off', 'disabled', 'false', '0'], true)) {
            return __('business.zheart.allowlist_off');
        }

        return '—';
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private static function formatExpiry(mixed $value, ?int $warnDays = 30): array
    {
        if ($value === null || $value === '' || $value === '—') {
            return ['—', false];
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return [self::displayScalar($value), false];
        }

        $label = $date->timezone(config('app.timezone'))->translatedFormat('Y-m-d H:i');
        $soon = $warnDays !== null && $date->isFuture() && $date->lessThanOrEqualTo(now()->addDays($warnDays));

        return [$label, $soon];
    }

    private static function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }

        try {
            /** @var CarbonInterface $date */
            $date = Carbon::parse($value);

            return $date->timezone(config('app.timezone'))->translatedFormat('Y-m-d H:i');
        } catch (\Throwable) {
            return self::displayScalar($value);
        }
    }
}