<?php

namespace App\Support\Invoicing;

/**
 * Maps Z-Heart invoice API `data` payloads (see ashique19/z-heart docs/API-CLIENT-CONTRACT.md)
 * into plumber's persisted ZATCA fields.
 */
final class ZHeartZatcaPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $data  Z-Heart response `data` object
     * @return array{
     *     reporting_status: ?string,
     *     remote_submission: array<string, mixed>,
     *     zatca_http_status: ?int,
     *     zatca_ok: ?bool,
     *     delivery: string,
     * }
     */
    public static function normalize(array $data): array
    {
        $zatca = is_array($data['zatca'] ?? null) ? $data['zatca'] : [];
        $body = is_array($zatca['body'] ?? null) ? $zatca['body'] : [];

        $clearanceRaw = $body['clearanceStatus'] ?? $body['clearance_status'] ?? null;
        $reportingRaw = $data['reporting_status'] ?? $body['reportingStatus'] ?? $body['reporting_status'] ?? null;
        $legacyZatcaStatus = is_string($zatca['status'] ?? null) ? $zatca['status'] : null;

        $effective = self::normalizeStatus(
            is_string($clearanceRaw) ? $clearanceRaw
                : (is_string($reportingRaw) ? $reportingRaw : $legacyZatcaStatus)
        );

        $validation = is_array($body['validationResults'] ?? null) ? $body['validationResults'] : [];
        $validationStatus = is_scalar($validation['status'] ?? null) ? (string) $validation['status'] : null;

        $errors = self::validationLines($validation, ['errorMessages', 'errors']);
        $warnings = self::validationLines($validation, ['warningMessages', 'warnings']);

        $zatcaOk = array_key_exists('ok', $zatca) ? (bool) $zatca['ok'] : null;
        $zatcaHttp = is_numeric($zatca['http_status'] ?? null) ? (int) $zatca['http_status'] : null;

        $delivery = 'sync';
        if ($zatcaOk === false) {
            $delivery = 'rejected';
        } elseif (in_array($effective, ['pending', 'queued', 'processing', ''], true) || $effective === null) {
            $delivery = filled($data['cleared_xml'] ?? null) ? 'sync' : 'pending';
        }

        if ($validationStatus !== null && strtoupper($validationStatus) === 'NOT_IMPLEMENTED') {
            $delivery = 'stub';
        }

        $remoteSubmission = array_filter([
            'status' => $effective,
            'clearance_status' => is_string($clearanceRaw) ? $clearanceRaw : null,
            'reporting_status' => is_string($reportingRaw) ? $reportingRaw : null,
            'message' => is_string($zatca['message'] ?? null) ? $zatca['message'] : null,
            'zatca_ok' => $zatcaOk,
            'zatca_http_status' => $zatcaHttp,
            'validation_status' => $validationStatus,
            'errors' => $errors !== [] ? $errors : null,
            'warnings' => $warnings !== [] ? $warnings : null,
            'delivery' => $delivery,
        ], fn (mixed $v) => $v !== null && $v !== []);

        return [
            'reporting_status' => $effective,
            'remote_submission' => $remoteSubmission,
            'zatca_http_status' => $zatcaHttp,
            'zatca_ok' => $zatcaOk,
            'delivery' => $delivery,
        ];
    }

    public static function normalizeStatus(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            'cleared', 'clear' => 'cleared',
            'reported' => 'reported',
            'accepted' => 'accepted',
            'pending', 'not_cleared', 'not-cleared' => 'pending',
            'queued', 'processing', 'in_progress' => 'pending',
            'rejected', 'failed', 'error' => 'rejected',
            'warning', 'warnings' => 'warning',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function validationLines(array $validation, array $keys): array
    {
        $messages = [];
        foreach ($keys as $key) {
            $items = $validation[$key] ?? [];
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $messages[] = trim($item);
                } elseif (is_array($item)) {
                    foreach (['message', 'reason', 'detail', 'description', 'code', 'category'] as $field) {
                        if (is_scalar($item[$field] ?? null) && trim((string) $item[$field]) !== '') {
                            $messages[] = trim((string) $item[$field]);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($messages));
    }
}