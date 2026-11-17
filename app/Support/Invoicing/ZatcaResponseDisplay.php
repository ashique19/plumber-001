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
        $errors = self::collectList($raw, ['errors', 'error', 'validation_errors', 'rejection_reasons']);

        $lastError = data_get($invoice->payload, 'zheart_last_error');
        if (is_string($lastError) && $lastError !== '' && ! in_array($lastError, $errors, true)) {
            $errors[] = $lastError;
        }

        return [
            'status' => $status,
            'submitted_at' => is_string(data_get($invoice->payload, 'zheart_zatca_submitted_at'))
                ? data_get($invoice->payload, 'zheart_zatca_submitted_at')
                : (is_string(data_get($invoice->payload, 'zheart_last_error_at')) ? data_get($invoice->payload, 'zheart_last_error_at') : null),
            'request_id' => is_string(data_get($invoice->payload, 'zheart_request_id')) ? data_get($invoice->payload, 'zheart_request_id') : null,
            'messages' => $messages,
            'warnings' => $warnings,
            'errors' => $errors,
            'details' => $raw,
        ];
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