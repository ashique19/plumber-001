<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DocumentCreateUiSupport
{
    /** @var list<float> */
    public const VAT_SELECT_RATES = [0.0, 5.0, 15.0];

    public static function newLineId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function assignMissingLineIds(array &$lines): void
    {
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            if (! filled($line['line_id'] ?? null)) {
                $lines[$index]['line_id'] = self::newLineId();
            }
        }
    }

    public static function defaultDocumentVatRate(): float
    {
        return 15.0;
    }

    /**
     * Normalizes a VAT rate for &lt;select&gt; options (0 / 5 / 15) so UI matches totals.
     */
    public static function normalizeLineVatRate(mixed $lineValue, mixed $productVat = null, ?float $fallback = null): string
    {
        $fallback ??= self::defaultDocumentVatRate();
        $raw = trim((string) ($lineValue ?? ''));

        if ($raw === '') {
            $rate = $productVat !== null && trim((string) $productVat) !== ''
                ? (float) $productVat
                : $fallback;
        } else {
            $rate = (float) $raw;
        }

        if ($productVat !== null && trim((string) $productVat) !== '') {
            $product = (float) $productVat;
            if ($raw === '' || (abs($rate) < 0.001 && $product > 0)) {
                $rate = $product;
            }
        }

        return self::vatRateOptionValue($rate);
    }

    public static function vatRateOptionValue(float $rate): string
    {
        foreach (self::VAT_SELECT_RATES as $allowed) {
            if (abs($rate - $allowed) < 0.011) {
                return (string) (int) $allowed;
            }
        }

        if (fmod($rate, 1.0) === 0.0) {
            return (string) (int) $rate;
        }

        return rtrim(rtrim(number_format(max(0, $rate), 2, '.', ''), '0'), '.');
    }
    /**
     * @param  array<string, float>  $previewTotals
     * @return array<string, float>
     */
    public static function summaryRows(array $previewTotals): array
    {
        $subtotal = (float) ($previewTotals['subtotal_ex_vat'] ?? 0);
        $discount = (float) ($previewTotals['invoice_discount_ex_vat'] ?? 0);
        $taxable = round(max(0, $subtotal - $discount), 2);

        return [
            'subtotal_ex_vat' => $subtotal,
            'discount_ex_vat' => $discount,
            'taxable_ex_vat' => $taxable,
            'total_vat' => (float) ($previewTotals['total_vat'] ?? 0),
            'grand_total_inc_vat' => (float) ($previewTotals['total_inc_vat'] ?? 0),
        ];
    }

    public static function defaultQuotationTerms(BusinessSetting $business): string
    {
        $stored = data_get($business->settings, 'quotations.default_terms');

        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        return (string) __('quotations.default_terms_body');
    }

    /**
     * @return array{
     *     legal_name: string,
     *     legal_name_ar: string,
     *     vat_number: string,
     *     address_line: string,
     *     phone: string,
     *     email: string
     * }|null
     */
    public static function customerStrip(
        ?int $selectedCustomerId,
        string $buyerName,
        string $buyerVat,
        string $buyerPhone,
        string $buyerEmail,
        string $addrBuilding,
        string $addrStreet,
        string $addrDistrict,
        string $addrCity,
        string $addrPostal,
        string $addrAdditional,
    ): ?array {
        if ($selectedCustomerId === null && trim($buyerName) === '') {
            return null;
        }

        $customer = $selectedCustomerId !== null
            ? Customer::query()->find($selectedCustomerId)
            : null;

        $profile = is_array($customer?->profile) ? $customer->profile : [];
        $nameAr = trim((string) ($profile['name_ar'] ?? ''));

        if ($nameAr === '' && $customer !== null) {
            $nameAr = trim((string) data_get($profile, 'legal_name_ar', ''));
        }

        $address = self::formatNationalAddress(
            $addrBuilding,
            $addrStreet,
            $addrDistrict,
            $addrCity,
            $addrPostal,
            $addrAdditional,
        );

        if ($address === '' && $customer !== null) {
            $na = (array) data_get($profile, 'national_address', []);
            $address = self::formatNationalAddress(
                (string) ($na['building'] ?? ''),
                (string) ($na['street'] ?? ''),
                (string) ($na['district'] ?? ''),
                (string) ($na['city'] ?? ''),
                (string) ($na['postal_code'] ?? ''),
                (string) ($na['additional'] ?? ''),
            );
        }

        return [
            'legal_name' => trim($buyerName) !== '' ? trim($buyerName) : (string) ($customer?->name ?? ''),
            'legal_name_ar' => $nameAr,
            'vat_number' => trim($buyerVat) !== ''
                ? trim($buyerVat)
                : trim((string) ($customer?->vat_number ?? '')),
            'address_line' => $address,
            'phone' => trim($buyerPhone) !== '' ? trim($buyerPhone) : trim((string) ($customer?->phone ?? '')),
            'email' => trim($buyerEmail) !== '' ? trim($buyerEmail) : trim((string) ($customer?->email ?? '')),
        ];
    }

    public static function formatNationalAddress(
        string $building,
        string $street,
        string $district,
        string $city,
        string $postal,
        string $additional,
    ): string {
        $parts = array_values(array_filter([
            trim($building),
            trim($street),
            trim($district),
            trim($city),
            trim($postal),
            trim($additional),
        ], fn (string $v): bool => $v !== ''));

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{line_ex_vat: float, line_inc_vat: float}
     */
    public static function lineAmounts(array $line): array
    {
        $qty = max(0, (float) ($line['quantity'] ?? 0));
        $unit = max(0, (float) ($line['unit_price'] ?? 0));
        $vat = max(0, (float) ($line['vat_rate'] ?? 0));
        $discountPct = max(0, (float) ($line['discount_percent'] ?? 0));
        $discountEx = round($qty * $unit * $discountPct / 100, 2);

        $data = new InvoiceLineData(
            name: (string) ($line['name'] ?? ''),
            quantity: $qty,
            unitPriceExVat: $unit,
            vatRate: $vat,
            discountExVat: $discountEx,
            nameAr: filled($line['name_ar'] ?? null) ? (string) $line['name_ar'] : null,
        );

        return [
            'line_ex_vat' => $data->netExVat(),
            'line_inc_vat' => $data->lineIncVat(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{quantity: float, unit_label: string, product_name: string}|null
     */
    public static function stockHintForLines(array $lines): ?array
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $row = $lines[$i];
            if (! filled($row['product_id'] ?? null)) {
                continue;
            }

            $product = Product::query()->find((int) $row['product_id']);
            if ($product === null || ! $product->track_stock) {
                return null;
            }

            return [
                'quantity' => (float) $product->stock_quantity,
                'unit_label' => $product->unitPickerLabel(),
                'product_name' => $product->displayName(),
            ];
        }

        return null;
    }

    public static function validityDaysBetween(string $fromDate, string $untilDate): int
    {
        try {
            $from = Carbon::parse($fromDate, 'Asia/Riyadh')->startOfDay();
            $until = Carbon::parse($untilDate, 'Asia/Riyadh')->startOfDay();

            return max(0, (int) $from->diffInDays($until, false));
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function pendingInvoiceNumberLabel(): string
    {
        return (string) __('invoices.document_number_pending');
    }

    public static function pendingQuotationNumberLabel(): string
    {
        return (string) __('quotations.document_number_pending');
    }
}