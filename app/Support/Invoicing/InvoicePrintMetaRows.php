<?php

namespace App\Support\Invoicing;

/**
 * Shared document meta rows for invoice print / PDF templates.
 */
final class InvoicePrintMetaRows
{
    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function sellerIdRows(array $t, bool $includeUnified = false): array
    {
        $rows = [];

        if (filled($t['trn'] ?? null)) {
            $rows[] = ['en' => 'VAT No.', 'ar' => 'الرقم الضريبي', 'value' => (string) $t['trn'], 'nowrap' => true, 'label_nowrap' => true];
        }

        if ($includeUnified && filled($t['unified_number'] ?? null)) {
            $rows[] = ['en' => 'Unified No.', 'ar' => 'الرقم الموحد', 'value' => (string) $t['unified_number'], 'nowrap' => true, 'label_nowrap' => true];
        }

        if (filled($t['cr_number'] ?? null)) {
            $rows[] = ['en' => 'CR No.', 'ar' => 'السجل التجاري', 'value' => (string) $t['cr_number'], 'nowrap' => true, 'label_nowrap' => true];
        }

        if (filled($t['mobile'] ?? null)) {
            $mobile = (string) $t['mobile'];
            if (filled($t['mobile_secondary'] ?? null)) {
                $mobile .= ' · '.(string) $t['mobile_secondary'];
            }
            $rows[] = ['en' => 'Mobile', 'ar' => 'الجوال', 'value' => $mobile, 'nowrap' => true, 'label_nowrap' => true];
        }

        return $rows;
    }

    /**
     * Seller IDs plus document meta for cash / thermal layout.
     *
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function cashIntroGrid(array $t): array
    {
        return self::filterFilled(array_merge(self::sellerIdRows($t), self::cashMeta($t)));
    }

    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function legacyA4IntroGrid(array $t): array
    {
        return self::filterFilled(array_merge(self::sellerIdRows($t), self::legacyA4Meta($t)));
    }

    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function modernIntroGrid(array $t): array
    {
        return self::filterFilled(array_merge(self::sellerIdRows($t, includeUnified: true), self::modernInvoiceMeta($t)));
    }

    /**
     * @return list<array{en: string, ar: string, value?: ?string, html?: string, nowrap?: bool, label_nowrap?: bool}>
     */
    public static function cashMeta(array $t): array
    {
        $rows = [
            ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'value' => (string) ($t['document_number'] ?? ''), 'label_nowrap' => true],
            ['en' => 'Issue date', 'ar' => 'تاريخ ووقت الإصدار', 'value' => $t['issue_datetime_print'] ?? null, 'label_nowrap' => true, 'nowrap' => true],
        ];

        if (! ($t['is_simplified'] ?? false)) {
            $rows[] = ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => $t['supply_date'] ?? null];
        }

        $rows[] = [
            'en' => 'Payment',
            'ar' => 'الدفع',
            'html' => self::bidiSlashValue(
                (string) ($t['payment_display_en'] ?? ''),
                (string) ($t['payment_display_ar'] ?? ''),
            ),
            'nowrap' => true,
            'label_nowrap' => true,
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
        $simplified = (bool) ($t['is_simplified'] ?? false);
        $paymentLabelEn = 'Payment';
        $paymentLabelAr = $simplified ? 'الدفع' : 'شروط الدفع';

        $rows = [
            ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'value' => (string) ($t['document_number'] ?? ''), 'label_nowrap' => true],
            ['en' => 'Issue date', 'ar' => 'تاريخ ووقت الإصدار', 'value' => $t['issue_datetime_print'] ?? null, 'label_nowrap' => true, 'nowrap' => true],
            ['en' => $paymentLabelEn, 'ar' => $paymentLabelAr, 'value' => $t['payment_terms_en'] ?? null, 'label_nowrap' => true, 'nowrap' => true],
            ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null, 'nowrap' => true, 'label_nowrap' => true],
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

    private static function bidiSlashValue(string $leading, string $trailing): string
    {
        $leading = trim($leading);
        $trailing = trim($trailing);

        if ($leading === '' && $trailing === '') {
            return '';
        }

        if ($leading === '' || $trailing === '') {
            return e($leading !== '' ? $leading : $trailing);
        }

        return '<bdi dir="ltr" class="invoice-print-bidi-value">'.e($leading).' / '.e($trailing).'</bdi>';
    }
}
