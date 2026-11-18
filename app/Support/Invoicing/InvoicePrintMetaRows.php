<?php

namespace App\Support\Invoicing;

/**
 * Shared document meta rows for invoice print / PDF templates.
 */
final class InvoicePrintMetaRows
{
    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool}>
     */
    public static function cashMeta(array $t): array
    {
        $rows = [
            ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'value' => (string) ($t['document_number'] ?? ''), 'nowrap' => true],
            ['en' => 'Issue date & time', 'ar' => 'تاريخ ووقت الإصدار', 'value' => $t['issue_datetime_print'] ?? null],
        ];

        if (! ($t['is_simplified'] ?? false)) {
            $rows[] = ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => $t['supply_date'] ?? null];
        }

        $rows[] = [
            'en' => 'Payment',
            'ar' => 'الدفع',
            'html' => e((string) ($t['payment_display_en'] ?? '')).' / '.e((string) ($t['payment_display_ar'] ?? '')),
        ];

        return self::filterFilled($rows);
    }

    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool}>
     */
    public static function legacyA4Meta(array $t): array
    {
        $rows = self::cashMeta($t);
        $rows[] = ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null];

        return self::filterFilled($rows);
    }

    /**
     * Two-column meta grid for modern / default A4 (non-quotation).
     *
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool}>
     */
    public static function modernInvoiceMeta(array $t): array
    {
        $rows = [
            ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'value' => (string) ($t['document_number'] ?? ''), 'nowrap' => true],
            ['en' => 'Issue date & time', 'ar' => 'تاريخ ووقت الإصدار', 'value' => $t['issue_datetime_print'] ?? null],
            ['en' => 'Payment terms', 'ar' => 'شروط الدفع', 'value' => $t['payment_terms_en'] ?? null],
            ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null],
        ];

        if (! ($t['is_simplified'] ?? false)) {
            $rows[] = ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => $t['supply_date'] ?? null];
        }

        return self::filterFilled($rows);
    }

    /**
     * @param  list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool}>  $rows
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool}>
     */
    private static function filterFilled(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => filled($row['value'] ?? null) || filled($row['html'] ?? null),
        ));
    }
}
