<?php

namespace App\Support\Invoicing;

/**
 * Collects user-visible validation lines from Z-Heart API JSON (422 and similar).
 */
final class ZHeartApiValidationErrors
{
    /**
     * @return list<string>
     */
    public static function collect(?array $json): array
    {
        if ($json === null || $json === []) {
            return [];
        }

        $lines = [];

        foreach ([
            $json['errors'] ?? null,
            data_get($json, 'data.errors'),
            data_get($json, 'data.validation'),
            data_get($json, 'data.validation_errors'),
            data_get($json, 'data.details'),
        ] as $block) {
            $lines = array_merge($lines, self::flattenErrorsBlock($block));
        }

        $zatcaBody = data_get($json, 'data.zatca.body');
        if (is_array($zatcaBody)) {
            $validation = $zatcaBody['validationResults'] ?? null;
            if (is_array($validation)) {
                foreach (['errorMessages', 'errors', 'warningMessages', 'warnings'] as $key) {
                    $items = $validation[$key] ?? [];
                    if (is_array($items)) {
                        $lines = array_merge($lines, self::messageItems($items));
                    }
                }
            }
        }

        $dataMessage = data_get($json, 'data.message');
        if (is_string($dataMessage) && trim($dataMessage) !== '') {
            $lines[] = trim($dataMessage);
        }

        return self::presentLines(self::dedupe($lines));
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function presentLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }
            $presented = self::present($line);
            if ($presented === '') {
                continue;
            }
            $out[] = $presented;
        }

        return self::dedupe($out);
    }

    public static function present(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        $line = self::stripNoisePrefixes($line);

        if (str_contains($line, ':')) {
            [$field, $message] = explode(':', $line, 2);

            return self::humanizeFieldMessage(trim($field), trim($message));
        }

        return self::humanizeBareMessage($line);
    }

    /**
     * @param  list<string>  $fieldErrors
     */
    /**
     * Redacted ZATCA / Z-Heart error codes for logs (no buyer or line detail).
     *
     * @return list<string>
     */
    public static function codesForLog(?array $json): array
    {
        if ($json === null || $json === []) {
            return [];
        }

        $codes = [];
        $candidates = [
            data_get($json, 'data.error'),
            data_get($json, 'data.code'),
            data_get($json, 'error'),
            data_get($json, 'code'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                $codes[] = trim($value);
            }
        }

        $zatcaBody = data_get($json, 'data.zatca.body');
        if (is_array($zatcaBody)) {
            $validation = $zatcaBody['validationResults'] ?? null;
            if (is_array($validation)) {
                foreach (['errorMessages', 'errors'] as $key) {
                    $items = $validation[$key] ?? [];
                    if (! is_array($items)) {
                        continue;
                    }
                    foreach ($items as $item) {
                        if (is_array($item)) {
                            foreach (['code', 'category', 'type'] as $field) {
                                $code = $item[$field] ?? null;
                                if (is_string($code) && trim($code) !== '') {
                                    $codes[] = trim($code);
                                }
                            }
                        }
                    }
                }
            }
        }

        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach (array_keys($errors) as $field) {
                if (is_string($field) && trim($field) !== '') {
                    $codes[] = trim($field);
                }
            }
        }

        return self::dedupe($codes);
    }

    public static function formatFlashMessage(string $summary, array $fieldErrors): string
    {
        $summary = trim($summary);
        $fieldErrors = self::presentLines($fieldErrors);

        if ($fieldErrors === []) {
            return $summary;
        }

        $lines = [$summary];
        foreach ($fieldErrors as $trimmed) {
            if ($trimmed === '' || self::sameMeaning($trimmed, $summary)) {
                continue;
            }
            $lines[] = '• '.$trimmed;
        }

        return implode("\n", self::dedupe($lines));
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function dedupe(array $lines): array
    {
        $out = [];
        $seen = [];

        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $key = mb_strtolower($trimmed);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $trimmed;
        }

        return $out;
    }

    private static function sameMeaning(string $a, string $b): bool
    {
        if (mb_strtolower($a) === mb_strtolower($b)) {
            return true;
        }

        $generic = [
            'the invoice payload is invalid.',
            'the invoice payload is invalid',
        ];

        $aLower = mb_strtolower($a);
        $bLower = mb_strtolower($b);

        foreach ($generic as $phrase) {
            if ($aLower === $phrase && str_contains($bLower, $phrase)) {
                return true;
            }
            if ($bLower === $phrase && str_contains($aLower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function flattenErrorsBlock(mixed $block): array
    {
        if (! is_array($block) || $block === []) {
            return [];
        }

        $lines = [];
        if (array_is_list($block)) {
            return self::messageItems($block);
        }

        foreach ($block as $field => $messages) {
            $fieldLabel = is_string($field) ? trim($field) : 'field';
            if (is_string($messages)) {
                $lines[] = self::fieldLine($fieldLabel, $messages);

                continue;
            }
            if (! is_array($messages)) {
                continue;
            }
            if (array_is_list($messages)) {
                foreach ($messages as $message) {
                    if (is_string($message)) {
                        $lines[] = self::fieldLine($fieldLabel, $message);
                    }
                }

                continue;
            }
            $lines = array_merge($lines, self::flattenErrorsBlock($messages));
        }

        return $lines;
    }

    private static function fieldLine(string $field, string $message): string
    {
        return self::humanizeFieldMessage(trim($field), trim($message));
    }

    private static function humanizeFieldMessage(string $field, string $message): string
    {
        $message = self::stripNoisePrefixes($message);
        $mapped = self::mapFieldToPrecheckKey($field);
        if ($mapped !== null) {
            return __('invoices.zatca_precheck.'.$mapped);
        }

        if ($message !== '' && (preg_match('/^(the |buyer |invoice\.)/i', $message) || ! str_contains($field, '.'))) {
            return $message;
        }

        if ($message !== '' && $field !== '' && ! str_contains(mb_strtolower($message), mb_strtolower($field))) {
            return $message;
        }

        return $message !== '' ? $message : $field;
    }

    private static function humanizeBareMessage(string $message): string
    {
        $message = self::stripNoisePrefixes($message);
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'postal') && (str_contains($lower, 'required') || str_contains($lower, 'missing'))) {
            return __('invoices.zatca_precheck.buyer_postal');
        }
        if (str_contains($lower, 'country') && str_contains($lower, 'required')) {
            return __('invoices.zatca_precheck.buyer_address_country_sa');
        }

        return $message;
    }

    private static function mapFieldToPrecheckKey(string $field): ?string
    {
        $normalized = strtolower(str_replace(['invoice.', 'buyer.'], '', $field));

        return match (true) {
            str_contains($normalized, 'address.postal'),
            str_contains($normalized, 'postal_zone'),
            str_ends_with($normalized, 'postal'),
            str_contains($normalized, 'postalzone') => 'buyer_postal',
            str_contains($normalized, 'address.country') => 'buyer_address_country_sa',
            str_contains($normalized, 'address.district'),
            str_contains($normalized, 'subdivision') => 'buyer_district',
            str_contains($normalized, 'address.street') => 'buyer_street',
            str_contains($normalized, 'address.building'),
            str_contains($normalized, 'buildingnumber') => 'buyer_building',
            str_contains($normalized, 'address.city') => 'buyer_city',
            str_contains($normalized, 'cr_number') => 'buyer_cr',
            str_contains($normalized, 'vat_number'),
            str_contains($normalized, 'vat') => 'buyer_vat',
            str_contains($normalized, 'supply_date') => 'supply_date',
            default => null,
        };
    }

    private static function stripNoisePrefixes(string $text): string
    {
        $text = trim($text);

        return preg_replace('/^(messages|rules):\s*/i', '', $text) ?? $text;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private static function messageItems(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $lines[] = trim($item);

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            foreach (['message', 'reason', 'detail', 'description', 'code', 'category'] as $field) {
                $value = $item[$field] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $lines[] = trim($value);
                    break;
                }
            }
        }

        return $lines;
    }
}