<?php

namespace App\Support\Invoicing;

use App\Models\Branch;
use App\Models\PosOrder;
use App\Support\BusinessProfile;

/**
 * Cash/thermal receipt view-model. Does not alter A4 or PDF presenter output.
 */
final class InvoiceCashReceiptView
{
    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    public static function fromPrintData(array $t, ?PosOrder $order = null): array
    {
        $na = (array) ($t['national_address'] ?? []);
        $sellerEnLines = $na !== []
            ? BusinessProfile::formatNationalAddressEnPrintLines($na)
            : self::fallbackLines($t['address_en'] ?? null);
        $sellerArLines = $na !== []
            ? BusinessProfile::formatNationalAddressArPdfLineHtml($na)
            : self::fallbackLines($t['address_ar'] ?? null);

        $lines = [];
        $itemCount = 0.0;
        foreach ((array) ($t['lines'] ?? []) as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $itemCount += $qty;
            $lineInc = (float) ($line['line_amount_inc_vat'] ?? 0);
            $unitInc = $qty > 0 ? round($lineInc / $qty, 2) : $lineInc;
            $lines[] = [
                'name_en' => (string) ($line['name_en'] ?? ''),
                'name_ar' => trim((string) ($line['name_ar'] ?? '')) ?: null,
                'quantity_display' => (string) ($line['quantity_display'] ?? InvoiceLineMath::formatQuantity($qty)),
                'unit_price' => $unitInc,
                'line_amount' => $lineInc,
                'vat_rate' => (float) ($line['vat_rate'] ?? 15),
                'taxable_ex_vat' => (float) ($line['taxable_ex_vat'] ?? 0),
                'vat_amount' => (float) ($line['vat_amount'] ?? 0),
            ];
        }

        $totals = (array) ($t['totals'] ?? []);
        $totalInc = (float) ($totals['total_inc_vat'] ?? $t['total_inc_vat'] ?? 0);
        $paid = self::amountPaid($t, $totalInc);
        $change = round($paid - $totalInc, 2);
        if ($change < 0.005) {
            $change = 0.0;
        }

        $titles = self::titles($t);
        $branch = $t['branch'] ?? null;
        $branchName = $branch instanceof Branch
            ? (trim((string) $branch->name) !== '' ? (string) $branch->name : null)
            : null;

        $customer = is_array($t['customer'] ?? null) ? $t['customer'] : [];
        $customerName = trim((string) ($customer['name'] ?? $customer['name_en'] ?? ''));
        $customerPhone = trim((string) ($customer['phone'] ?? ''));

        return [
            'titles' => $titles,
            'seller_en_lines' => $sellerEnLines,
            'seller_ar_lines' => $sellerArLines,
            'lines' => $lines,
            'item_count' => $itemCount,
            'item_count_display' => InvoiceLineMath::formatQuantity($itemCount),
            'vat_rows' => self::vatRows($lines),
            'total_inc_vat' => $totalInc,
            'amount_paid' => $paid,
            'change' => $change,
            'payment_en' => (string) ($t['payment_display_en'] ?? 'Cash'),
            'payment_ar' => (string) ($t['payment_display_ar'] ?? 'نقداً'),
            'cashier_name' => self::cashierName($t, $order),
            'branch_name' => $branchName,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
            'barcode_value' => (string) ($t['document_number'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array{en: string, ar: string}
     */
    public static function titles(array $t): array
    {
        $titles = DocumentPrintTitles::forContext($t);

        return [
            'en' => trans('pos.receipt_title_'.self::titleKey($t).'_en', [], 'en') ?: $titles['en'],
            'ar' => trans('pos.receipt_title_'.self::titleKey($t).'_ar', [], 'ar') ?: $titles['ar'],
        ];
    }

    /**
     * @param  array<string, mixed>  $t
     */
    private static function titleKey(array $t): string
    {
        $docType = (string) ($t['invoice_document_type'] ?? '');
        $noteSimplified = (bool) ($t['note_uses_simplified'] ?? false);

        return match ($docType) {
            'credit_note' => $noteSimplified ? 'simplified_credit' : 'credit',
            'debit_note' => $noteSimplified ? 'simplified_debit' : 'debit',
            'standard' => 'standard',
            default => 'simplified',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{vat_percent: int, before_vat: float, incl_vat: float, vat: float}>
     */
    public static function vatRows(array $lines): array
    {
        $buckets = [];
        foreach ($lines as $line) {
            $rate = (int) round((float) ($line['vat_rate'] ?? 15));
            if (! isset($buckets[$rate])) {
                $buckets[$rate] = [
                    'vat_percent' => $rate,
                    'before_vat' => 0.0,
                    'incl_vat' => 0.0,
                    'vat' => 0.0,
                ];
            }
            $buckets[$rate]['before_vat'] += (float) ($line['taxable_ex_vat'] ?? 0);
            $buckets[$rate]['vat'] += (float) ($line['vat_amount'] ?? 0);
            $buckets[$rate]['incl_vat'] += (float) ($line['line_amount'] ?? 0);
        }

        foreach ($buckets as &$row) {
            $row['before_vat'] = round($row['before_vat'], 2);
            $row['incl_vat'] = round($row['incl_vat'], 2);
            $row['vat'] = round($row['vat'], 2);
        }
        unset($row);

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @param  array<string, mixed>  $t
     */
    private static function amountPaid(array $t, float $totalInc): float
    {
        $payloadPaid = $t['amount_paid'] ?? $t['tendered'] ?? null;
        if (is_numeric($payloadPaid)) {
            return round((float) $payloadPaid, 2);
        }

        return round($totalInc, 2);
    }

    /**
     * @param  array<string, mixed>  $t
     */
    private static function cashierName(array $t, ?PosOrder $order): ?string
    {
        $fromOrder = trim((string) ($order?->cashier?->name ?? ''));
        if ($fromOrder !== '') {
            return $fromOrder;
        }

        $fromT = trim((string) ($t['cashier_name'] ?? ''));

        return $fromT !== '' ? $fromT : null;
    }

    /**
     * @return list<string>
     */
    private static function fallbackLines(mixed $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
