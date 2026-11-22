<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Enums\ProductItemType;
use App\Enums\VatZatcaCategory;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    public static function shouldInferBuyerContactOverride(?int $customerId, string $buyerPhone, string $buyerEmail): bool
    {
        if ($customerId === null) {
            return false;
        }

        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return trim($buyerPhone) !== '' || trim($buyerEmail) !== '';
        }

        $customerPhone = trim((string) ($customer->phone ?? ''));
        $customerEmail = trim((string) ($customer->email ?? ''));
        $phone = trim($buyerPhone);
        $email = trim($buyerEmail);

        if ($phone !== '' && $phone !== $customerPhone) {
            return true;
        }

        if ($email !== '' && $email !== $customerEmail) {
            return true;
        }

        return false;
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


    public static function lineIsPresent(array $row): bool
    {
        if (filled($row['product_id'] ?? null)) {
            return true;
        }

        return filter_var($row['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isCustomLine(array $row): bool
    {
        return self::lineIsPresent($row) && ! filled($row['product_id'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyFormLine(): array
    {
        return [
            'line_id' => self::newLineId(),
            'name' => '',
            'name_ar' => '',
            'name_bn' => '',
            'quantity' => '1',
            'unit_price' => '',
            'vat_rate' => self::normalizeLineVatRate(null, self::defaultDocumentVatRate()),
            'discount_percent' => '0',
            'product_id' => null,
            'product_unit_id' => null,
            'product_size_id' => null,
            'unit_label' => '',
            'unit_code' => '',
            'item_type' => ProductItemType::Item->value,
            'vat_zatca_category' => VatZatcaCategory::Standard->value,
            'is_custom' => false,
            'product_image_url' => '',
            'track_stock' => false,
            'stock_quantity' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function decorateCustomLine(array $line): array
    {
        $line = array_merge(self::emptyFormLine(), $line);
        $line['is_custom'] = true;
        $line['product_id'] = null;
        $line['vat_rate'] = self::normalizeLineVatRate($line['vat_rate'] ?? '', self::defaultDocumentVatRate());
        if (trim((string) ($line['vat_zatca_category'] ?? '')) === '') {
            $line['vat_zatca_category'] = VatZatcaCategory::Standard->value;
        }
        if (trim((string) ($line['item_type'] ?? '')) === '') {
            $line['item_type'] = ProductItemType::Item->value;
        }
        if (! filled($line['product_unit_id'] ?? null)) {
            $unit = ProductUnit::query()->where('is_active', true)->where('code', 'PCS')->first()
                ?? ProductUnit::query()->where('is_active', true)->orderBy('code')->first();
            if ($unit !== null) {
                $line['product_unit_id'] = $unit->id;
                $line['unit_label'] = $unit->displayName();
                $line['unit_code'] = filled($unit->ubl_unit_code) ? (string) $unit->ubl_unit_code : 'PCE';
            }
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function formLineFromPayloadRow(array $row): array
    {
        $line = self::emptyFormLine();
        $line['name'] = (string) ($row['name'] ?? '');
        $line['name_ar'] = (string) ($row['name_ar'] ?? '');
        $line['name_bn'] = (string) ($row['name_bn'] ?? '');
        $line['quantity'] = (string) ($row['quantity'] ?? '1');
        $line['unit_price'] = (string) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? '');
        $line['vat_rate'] = self::normalizeLineVatRate($row['vat_rate'] ?? '15');
        $line['discount_percent'] = (string) ($row['discount_percent'] ?? '0');
        $line['product_id'] = filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null;
        $line['product_unit_id'] = filled($row['product_unit_id'] ?? null) ? (int) $row['product_unit_id'] : null;
        $line['unit_label'] = (string) ($row['unit_label'] ?? '');
        $line['unit_code'] = (string) ($row['unit_code'] ?? '');
        $line['item_type'] = (string) ($row['item_type'] ?? ProductItemType::Item->value);
        $line['vat_zatca_category'] = (string) ($row['vat_zatca_category'] ?? VatZatcaCategory::Standard->value);
        $line['is_custom'] = $line['product_id'] === null && (
            filter_var($row['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN) || trim($line['name']) !== ''
        );

        if ($line['is_custom'] && ! filled($line['product_unit_id'])) {
            $line = self::decorateCustomLine($line);
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function payloadLineFromFormRow(array $row): array
    {
        $item = [
            'name' => trim((string) ($row['name'] ?? '')),
            'quantity' => round((float) ($row['quantity'] ?? 0), 3),
            'unit_price_ex_vat' => (float) ($row['unit_price'] ?? 0),
            'vat_rate' => (float) ($row['vat_rate'] ?? 15),
        ];

        foreach (['name_ar', 'name_bn', 'unit_label', 'unit_code', 'item_type', 'vat_zatca_category'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $item[$key] = $value;
            }
        }

        $discount = max(0, (float) ($row['discount_percent'] ?? 0));
        if ($discount > 0) {
            $item['discount_percent'] = $discount;
        }

        $productId = $row['product_id'] ?? null;
        if ($productId !== null && (int) $productId > 0) {
            $item['product_id'] = (int) $productId;
        } else {
            $item['is_custom'] = true;
        }

        if (filled($row['product_unit_id'] ?? null)) {
            $item['product_unit_id'] = (int) $row['product_unit_id'];
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public static function lineValidationRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.is_custom' => ['sometimes', 'boolean'],
            'lines.*.name' => ['required', 'string', 'max:200'],
            'lines.*.name_ar' => ['required_without:lines.*.product_id', 'nullable', 'string', 'max:200'],
            'lines.*.name_bn' => ['nullable', 'string', 'max:200'],
            'lines.*.item_type' => ['nullable', 'string', Rule::in(array_column(ProductItemType::cases(), 'value'))],
            'lines.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'lines.*.unit_code' => ['nullable', 'string', 'max:16'],
            'lines.*.vat_zatca_category' => ['nullable', 'string', Rule::in(VatZatcaCategory::values())],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return list<array{id:int, label:string, code:string}>
     */
    public static function productUnitOptions(): array
    {
        return ProductUnit::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (ProductUnit $unit): array => [
                'id' => (int) $unit->id,
                'label' => $unit->displayName(),
                'code' => filled($unit->ubl_unit_code) ? (string) $unit->ubl_unit_code : 'PCE',
            ])
            ->all();
    }

    /**
     * @return list<array{value:string, label:string}>
     */
    public static function itemTypeOptions(): array
    {
        return array_map(
            fn (ProductItemType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            ProductItemType::cases(),
        );
    }

    /**
     * @return list<array{value:string, label:string}>
     */
    public static function vatZatcaCategoryOptions(): array
    {
        return array_map(
            fn (VatZatcaCategory $category): array => [
                'value' => $category->value,
                'label' => __('catalog.vat_zatca_'.$category->value),
            ],
            VatZatcaCategory::cases(),
        );
    }
}
