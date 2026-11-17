<?php

namespace App\Support\Invoicing;

use App\Models\Invoice;

final class ZatcaResponseDisplay
{
    /**
     * @return array{
     *     status: ?string,
     *     submitted_at: ?string,
     *     request_id: ?string,
     *     http_status: ?int,
     *     zatca_http_status: ?int,
     *     delivery: ?string,
     *     clearance_status: ?string,
     *     validation_status: ?string,
     *     messages: list<string>,
     *     warnings: list<string>,
     *     errors: list<string>,
     *     details: array<string, mixed>
     * }|null
     */
    public static function forInvoice(Invoice $invoice): ?array
    {
        $raw = data_get($invoice->payload, 'zheart_zatca_display');
        if (! is_array($raw) || $raw === []) {
            $raw = ZatcaResponseSanitizer::sanitizeForDisplay(
                is_array(data_get($invoice->payload, 'zheart_zatca')) ? data_get($invoice->payload, 'zheart_zatca') : null
            );
        }

        if ($raw === null || $raw === []) {
            return null;
        }

        $status = self::stringOrNull($raw['status'] ?? data_get($invoice->payload, 'zheart_reporting_status'));
        $messages = self::collectStrings($raw, ['message', 'detail', 'description']);
        $warnings = self::collectList($raw, ['warnings', 'warning', 'validation_warnings']);
        $fieldErrors = self::collectList($raw, ['field_errors']);
        $storedFieldErrors = data_get($invoice->payload, 'zheart_field_errors');
        if (is_array($storedFieldErrors)) {
            foreach ($storedFieldErrors as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $fieldErrors[] = trim($line);
                }
            }
        }

        $errors = self::collectList($raw, ['errors', 'error', 'validation_errors', 'rejection_reasons']);
        $errors = array_merge($fieldErrors, $errors);

        $lastError = data_get($invoice->payload, 'zheart_last_error');
        $errors = ZHeartApiValidationErrors::dedupe($errors);
        $messages = ZHeartApiValidationErrors::dedupe($messages);
        if (is_string($lastError) && $lastError !== '') {
            $summaryLower = mb_strtolower(trim($lastError));
            $errors = array_values(array_filter(
                $errors,
                function (string $line) use ($summaryLower): bool {
                    $lower = mb_strtolower(trim($line));

                    return $lower !== $summaryLower
                        && ! str_contains($summaryLower, $lower)
                        && ! (self::isGenericPayloadInvalid($line) && $lower !== '');
                },
            ));
            $messages = array_values(array_filter(
                $messages,
                fn (string $line): bool => mb_strtolower(trim($line)) !== $summaryLower
                    && ! self::isGenericPayloadInvalid($line),
            ));
        }

        $summary = is_string($lastError) && trim($lastError) !== '' ? trim($lastError) : null;

        return [
            'summary' => $summary,
            'status' => $status,
            'submitted_at' => is_string(data_get($invoice->payload, 'zheart_zatca_submitted_at'))
                ? data_get($invoice->payload, 'zheart_zatca_submitted_at')
                : (is_string(data_get($invoice->payload, 'zheart_last_error_at')) ? data_get($invoice->payload, 'zheart_last_error_at') : null),
            'request_id' => is_string(data_get($invoice->payload, 'zheart_request_id')) ? data_get($invoice->payload, 'zheart_request_id') : null,
            'http_status' => is_int(data_get($invoice->payload, 'zheart_last_http_status'))
                ? data_get($invoice->payload, 'zheart_last_http_status')
                : (is_numeric($raw['http_status'] ?? null) ? (int) $raw['http_status'] : null),
            'zatca_http_status' => is_int(data_get($invoice->payload, 'zheart_zatca_http_status'))
                ? data_get($invoice->payload, 'zheart_zatca_http_status')
                : (is_numeric($raw['zatca_http_status'] ?? null) ? (int) $raw['zatca_http_status'] : null),
            'delivery' => is_string(data_get($invoice->payload, 'zheart_zatca_delivery'))
                ? data_get($invoice->payload, 'zheart_zatca_delivery')
                : (is_string($raw['delivery'] ?? null) ? $raw['delivery'] : null),
            'clearance_status' => is_string($raw['clearance_status'] ?? null) ? $raw['clearance_status'] : null,
            'validation_status' => is_string($raw['validation_status'] ?? null) ? $raw['validation_status'] : null,
            'messages' => $messages,
            'warnings' => $warnings,
            'errors' => $errors,
            'field_errors' => ZHeartApiValidationErrors::dedupe($fieldErrors),
            'details' => $raw,
        ];
    }

    private static function isGenericPayloadInvalid(string $line): bool
    {
        $lower = mb_strtolower(trim($line));

        return in_array($lower, [
            'the invoice payload is invalid.',
            'the invoice payload is invalid',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function collectStrings(array $raw, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[] = trim($value);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function collectList(array $raw, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[] = trim($value);

                continue;
            }
            if (! is_array($value)) {
                continue;
            }
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $out[] = trim($item);
                } elseif (is_array($item)) {
                    foreach (['message', 'error', 'detail', 'code'] as $field) {
                        $text = $item[$field] ?? null;
                        if (is_string($text) && trim($text) !== '') {
                            $out[] = trim($text);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}