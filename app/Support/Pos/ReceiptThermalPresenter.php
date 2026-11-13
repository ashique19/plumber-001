<?php

namespace App\Support\Pos;

use App\Enums\InvoiceDocumentType;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\BusinessSetting;
use Illuminate\Support\Collection;

final class ReceiptThermalPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function compose(
        BusinessSetting $business,
        PosOrder $order,
        Invoice $invoice,
        ?Branch $branch,
    ): array {
        $legal = $business->settings['legal_entity'] ?? [];
        $theme = $business->settings['theme'] ?? [];
        $contentKey = $business->isPlumberTheme() ? 'plumber' : null;
        $content = $contentKey ? data_get($business->settings, "content.{$contentKey}", []) : [];

        $productSkus = self::productSkusForLines($order);
        $lines = self::mapLines($order->line_items ?? [], $productSkus);
        $itemCount = (int) array_sum(array_column($lines, 'quantity'));
        $vatRows = self::vatSummaryRows($lines);
        $paidAt = $order->paid_at?->timezone(config('app.timezone'));

        $customer = $order->relationLoaded('customer') ? $order->customer : null;
        $customerBlock = null;
        if ($customer !== null) {
            $maskedPhone = self::maskPhone($customer->phone);
            $customerBlock = array_filter([
                'masked_phone' => $maskedPhone,
                'name' => filled($customer->name) ? $customer->name : null,
            ]);
            if ($customerBlock === []) {
                $customerBlock = null;
            }
        }

        $paymentMeta = is_array($order->metadata) ? $order->metadata : [];
        $cardBlock = array_filter([
            'eft_trans_no' => $paymentMeta['eft_trans_no'] ?? $paymentMeta['eft_trans'] ?? null,
            'card_no' => isset($paymentMeta['card_no']) ? self::maskCard((string) $paymentMeta['card_no']) : null,
            'auth' => $paymentMeta['auth'] ?? $paymentMeta['auth_code'] ?? null,
        ]);

        $storefrontUrl = null;

        $branchLines = self::branchAddressLines($branch, $legal, $content);
        $contact = self::contactLines($legal, $content);

        $egs = $invoice->relationLoaded('egsDevice') ? $invoice->egsDevice : null;
        $posLabel = $egs?->id ? (string) $egs->id : ($invoice->egs_device_id ? (string) $invoice->egs_device_id : '—');
        $storeLabel = $branch?->code ?: ($branch?->id ? (string) $branch->id : '—');

        $isSimplified = $invoice->document_type === InvoiceDocumentType::Simplified
            || $invoice->document_type === null;

        return [
            'seller_en' => $legal['name_en'] ?? $business->name,
            'seller_ar' => $legal['name_ar'] ?? $business->name,
            'logo_url' => filled($theme['logo_url'] ?? null) ? $theme['logo_url'] : null,
            'branch_lines' => $branchLines,
            'phone' => $contact['phone'],
            'fax' => $contact['fax'],
            'email' => $contact['email'],
            'cr_number' => $legal['cr_number'] ?? null,
            'trn' => $legal['vat_number'] ?? null,
            'is_simplified' => $isSimplified,
            'customer' => $customerBlock,
            'lines' => $lines,
            'item_count' => $itemCount,
            'total_inc_vat' => (float) $order->total_inc_vat,
            'payment_display' => self::paymentLabel(
                $order->payment_method instanceof \BackedEnum
                    ? $order->payment_method->value
                    : (string) ($order->payment_method ?? 'cash')
            ),
            'card' => $cardBlock !== [] ? $cardBlock : null,
            'vat_rows' => $vatRows,
            'cashier_name' => $order->cashier?->name,
            'meta_table' => [
                'date' => $paidAt?->format('Y-m-d') ?? '—',
                'time' => $paidAt?->format('H:i:s') ?? '—',
                'store' => $storeLabel,
                'pos' => $posLabel,
                'receipt' => $order->order_number,
            ],
            'barcode_value' => $order->order_number,
            'storefront_url' => $storefrontUrl,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<array<string, mixed>>
     */
    private static function mapLines(array $rawLines, Collection $productSkus): array
    {
        $mapped = [];

        foreach ($rawLines as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $unitEx = (float) ($line['unit_price_ex_vat'] ?? 0);
            $discountEx = (float) ($line['discount_ex_vat'] ?? 0);
            $vatRate = (float) ($line['vat_rate'] ?? 15);
            $lineEx = max(0, $unitEx * $qty - $discountEx);
            $lineVat = round($lineEx * $vatRate / 100, 2);
            $lineInc = round($lineEx + $lineVat, 2);
            $unitInc = $qty > 0 ? round($lineInc / $qty, 2) : 0.0;

            $productId = $line['product_id'] ?? null;
            $sku = $productId !== null ? (string) ($productSkus->get((int) $productId) ?? '') : '';
            $barcode = $sku !== '' ? $sku : ($productId !== null ? (string) $productId : '');

            $mapped[] = [
                'barcode' => $barcode,
                'sku' => $sku,
                'quantity' => $qty,
                'unit_price_inc' => $unitInc,
                'tax_code' => self::taxCode($vatRate),
                'name_en' => (string) ($line['name'] ?? ''),
                'name_ar' => (string) ($line['name_ar'] ?? $line['name'] ?? ''),
                'line_total_inc' => $lineInc,
                'vat_rate' => $vatRate,
                'line_ex_vat' => round($lineEx, 2),
                'line_vat' => $lineVat,
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private static function vatSummaryRows(array $lines): array
    {
        $buckets = [];

        foreach ($lines as $line) {
            $code = $line['tax_code'];
            if (! isset($buckets[$code])) {
                $buckets[$code] = [
                    'tax_code' => $code,
                    'vat_percent' => (int) round($line['vat_rate']),
                    'before_vat' => 0.0,
                    'incl_vat' => 0.0,
                    'vat' => 0.0,
                ];
            }
            $buckets[$code]['before_vat'] += $line['line_ex_vat'];
            $buckets[$code]['vat'] += $line['line_vat'];
            $buckets[$code]['incl_vat'] += $line['line_total_inc'];
        }

        foreach ($buckets as &$row) {
            $row['before_vat'] = round($row['before_vat'], 2);
            $row['incl_vat'] = round($row['incl_vat'], 2);
            $row['vat'] = round($row['vat'], 2);
        }
        unset($row);

        return array_values($buckets);
    }

    private static function taxCode(float $vatRate): string
    {
        $rate = (int) round($vatRate);

        return match ($rate) {
            15 => 'S6',
            0 => 'Z',
            default => 'S'.$rate,
        };
    }

    private static function paymentLabel(?string $method): string
    {
        return match ($method) {
            'mada' => 'MADA',
            'card' => 'CARD',
            'cash' => 'CASH',
            default => strtoupper((string) ($method ?: 'CASH')),
        };
    }

    private static function maskPhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }
        $tail = substr($digits, -4);

        return str_repeat('*', max(7, strlen($digits) - 4)).$tail;
    }

    private static function maskCard(string $card): string
    {
        $digits = preg_replace('/\D+/', '', $card) ?? '';
        if (strlen($digits) < 8) {
            return $card;
        }

        return substr($digits, 0, 6).str_repeat('*', strlen($digits) - 10).substr($digits, -4);
    }

    /**
     * @param  array<string, mixed>  $legal
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private static function branchAddressLines(?Branch $branch, array $legal, array $content): array
    {
        $lines = [];

        if ($branch !== null) {
            if (filled($branch->name)) {
                $lines[] = $branch->name;
            }
            if (filled($branch->address)) {
                $lines[] = $branch->address;
            }
        }

        if ($lines === [] && filled($legal['address'] ?? null)) {
            $lines[] = (string) $legal['address'];
        }

        if ($lines === [] && filled($legal['address_ar'] ?? null)) {
            $lines[] = (string) $legal['address_ar'];
        }

        if ($lines === [] && filled($content['location_badge_en'] ?? null)) {
            $lines[] = (string) $content['location_badge_en'];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $legal
     * @param  array<string, mixed>  $content
     * @return array{phone: ?string, fax: ?string, email: ?string}
     */
    private static function contactLines(array $legal, array $content): array
    {
        $legacy = (array) ($content['contact'] ?? []);
        $phone = $legacy['phone'] ?? $content['phone'] ?? $legal['phone'] ?? null;
        $email = $legacy['email'] ?? $content['email'] ?? $legal['email'] ?? null;

        return [
            'phone' => filled($phone) ? (string) $phone : null,
            'fax' => filled($legal['fax'] ?? null) ? (string) $legal['fax'] : null,
            'email' => filled($email) ? (string) $email : null,
        ];
    }

    private static function productSkusForLines(PosOrder $order): Collection
    {
        $ids = collect($order->line_items ?? [])
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->pluck('sku', 'id');
    }
}
