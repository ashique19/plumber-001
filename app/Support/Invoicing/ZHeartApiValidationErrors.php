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

        return self::dedupe($lines);
    }

    /**
     * @param  list<string>  $fieldErrors
     */
    public static function formatFlashMessage(string $summary, array $fieldErrors): string
    {
        $summary = trim($summary);
        if ($fieldErrors === []) {
            return $summary;
        }

        $lines = [$summary];
        foreach ($fieldErrors as $line) {
            $trimmed = trim($line);
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
        $message = trim($message);
        $field = trim($field);

        if ($field === '' || str_contains(mb_strtolower($message), mb_strtolower($field))) {
            return $message;
        }

        return $field.': '.$message;
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