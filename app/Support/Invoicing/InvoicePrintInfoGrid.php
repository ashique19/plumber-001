<?php

namespace App\Support\Invoicing;

/**
 * Column widths for the bilingual document meta grid (screen, print, PDF).
 */
final class InvoicePrintInfoGrid
{
    public const COL_EN_PCT = 28;

    public const COL_VALUE_PCT = 46;

    public const COL_AR_PCT = 26;

    /**
     * Fixture rows exercising the longest EN / AR label strings used in production templates.
     *
     * @return list<array{en: string, ar: string, value: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function longLabelFixtureRows(): array
    {
        return [
            ['en' => 'Quotation number', 'ar' => 'رقم العرض', 'value' => 'HARNESS-QUO-LABEL-001', 'nowrap' => true, 'label_nowrap' => true],
            ['en' => 'Payment terms', 'ar' => 'شروط الدفع', 'value' => 'Net 30 days', 'nowrap' => true, 'label_nowrap' => true],
            ['en' => 'Issue date & time', 'ar' => 'تاريخ ووقت الإصدار', 'value' => '2026-11-18 15:00', 'nowrap' => true, 'label_nowrap' => true],
            ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => '2026-11-18', 'nowrap' => true, 'label_nowrap' => true],
        ];
    }
}