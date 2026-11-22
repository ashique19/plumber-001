<?php

namespace App\Support\Invoicing;

/**
 * Column widths for the bilingual document meta grid (screen, print, PDF).
 */
final class InvoicePrintInfoGrid
{
    /**
     * Percent of the full 6-column grid (each bilingual pair occupies 50%).
     *
     * Content-driven shares (each bilingual pair occupies 50%). AR is 15% so
     * "تاريخ ووقت الإصدار" fits on one line at 8pt with 2.5–3pt padding. Value
     * is 25% so 9.5pt "Cash (due on invoice date)" stays on one line. EN is
     * 10% so "Supply date" / "Invoice No." stay on one line. A long invoice
     * number may still wrap at hyphens.
     */
    public const COL_EN_PCT = 10;

    public const COL_VALUE_PCT = 25;

    public const COL_AR_PCT = 15;

    /** Same shares as COL_* so the PDF overlay matches the HTML grid. */
    public const PDF_COL_EN_PCT = 10;

    public const PDF_COL_VALUE_PCT = 25;

    public const PDF_COL_AR_PCT = 15;

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