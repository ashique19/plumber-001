<?php

namespace App\Support\Pos;

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Invoicing\InvoiceLineMath;
use App\Support\Invoicing\SarAmountInWords;
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
        $legal = \App\Support\Invoicing\ZHeartSellerLegal::forInvoice($business, $invoice);
        $theme = $business->settings['theme'] ?? [];
        $contentKey = $business->isPlumberTheme() ? 'plumber' : null;
        $content = $contentKey ? data_get($business->settings, "content.{$contentKey}", []) : [];
        $payload = $invoice->payload ?? [];

        $productSkus = self::productSkusForLines($order);
        $lines = self::mapLines($order->line_items ?? [], $productSkus);
        $itemCount = (float) array_sum(array_map(static fn (array $l): float => (float) $l['quantity'], $lines));
        $vatRows = self::vatSummaryRows($lines);
        $paidAt = $order->paid_at?->timezone(config('app.timezone'));

        $signingLines = self::signingLinesFromPayload($order->line_items ?? []);
        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;
        $totals = InvoiceDocumentTotals::compute($signingLines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        $customerBlock = self::customerBlock($order, $payload, $invoice);
        $paymentMeta = is_array($order->metadata) ? $order->metadata : [];
        $cardBlock = array_filter([
            'eft_trans_no' => $paymentMeta['eft_trans_no'] ?? $paymentMeta['eft_trans'] ?? null,
            'card_no' => isset($paymentMeta['card_no']) ? self::maskCard((string) $paymentMeta['card_no']) : null,
            'auth' => $paymentMeta['auth'] ?? $paymentMeta['auth_code'] ?? null,
        ]);

        $branchLines = self::branchAddressLines($branch, $legal, $content);
        $branchLinesAr = self::branchAddressLinesAr($branch, $legal);
        $contact = self::contactLines($legal, $content);

        $egs = $invoice->relationLoaded('egsDevice') ? $invoice->egsDevice : null;
        $posLabel = $egs?->id ? (string) $egs->id : ($invoice->egs_device_id ? (string) $invoice->egs_device_id : '—');
        $storeLabel = $branch?->code ?: ($branch?->id ? (string) $branch->id : '—');

        $isSimplified = $invoice->document_type === InvoiceDocumentType::Simplified
            || $invoice->document_type === null;

        $paymentMethod = $order->payment_method instanceof \BackedEnum
            ? $order->payment_method->value
            : (string) ($order->payment_method ?? 'cash');

        $amountWords = SarAmountInWords::format($totals['total_inc_vat']);

        return [
            'seller_en' => $legal['name_en'] ?? $business->name,
            'seller_ar' => $legal['name_ar'] ?? $business->name,
            'logo_url' => filled($theme['logo_url'] ?? null) ? $theme['logo_url'] : null,
            'branch_lines' => $branchLines,
            'branch_lines_ar' => $branchLinesAr,
            'phone' => $contact['phone'],
            'mobile' => $contact['mobile'],
            'mobile_secondary' => $contact['mobile_secondary'],
            'fax' => $contact['fax'],
            'email' => $contact['email'],
            'cr_number' => $legal['cr_number'] ?? null,
            'unified_number' => $legal['unified_number'] ?? null,
            'trn' => $legal['vat_number'] ?? null,
            'is_simplified' => $isSimplified,
            'customer' => $customerBlock,
            'lines' => $lines,
            'item_count' => $itemCount,
            'totals' => $totals,
            'total_inc_vat' => $totals['total_inc_vat'],
            'net_due_inc_vat' => $totals['net_due_inc_vat'],
            'payment_display' => self::paymentLabel($paymentMethod),
            'payment_method' => $paymentMethod,
            'card' => $cardBlock !== [] ? $cardBlock : null,
            'vat_rows' => $vatRows,
            'cashier_name' => $order->cashier?->name,
            'document_number' => $invoice->document_number,
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'amount_words_ar' => $amountWords['ar'],
            'amount_words_en' => $amountWords['en'],
            'meta_table' => [
                'date' => $paidAt?->format('Y-m-d') ?? '—',
                'time' => $paidAt?->format('H:i:s') ?? '—',
                'store' => $storeLabel,
                'pos' => $posLabel,
                'receipt' => $order->order_number,
            ],
            'barcode_value' => $order->order_number,
            'storefront_url' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<InvoiceLineData>
     */
    private static function signingLinesFromPayload(array $rawLines): array
    {
        $out = [];

        foreach ($rawLines as $line) {
            $nameAr = trim((string) ($line['name_ar'] ?? ''));

            $out[] = new InvoiceLineData(
                name: (string) ($line['name'] ?? 'Item'),
                quantity: (float) ($line['quantity'] ?? 1),
                unitPriceExVat: (float) ($line['unit_price_ex_vat'] ?? 0),
                vatRate: (float) ($line['vat_rate'] ?? 15),
                discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
                nameAr: $nameAr !== '' ? $nameAr : null,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function customerBlock(PosOrder $order, array $payload, Invoice $invoice): ?array
    {
        $customer = $order->relationLoaded('customer') ? $order->customer : null;
        $name = trim((string) ($payload['buyer_name'] ?? $customer?->name ?? ''));
        $phone = trim((string) ($payload['buyer_phone'] ?? $customer?->phone ?? ''));
        $email = trim((string) ($payload['buyer_email'] ?? $customer?->email ?? ''));
        $vat = trim((string) ($payload['buyer_vat'] ?? data_get($customer?->profile, 'vat_number', '') ?? ''));
        $address = self::formatBuyerAddress($payload);

        $block = array_filter([
            'name' => $name !== '' ? $name : null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'vat_number' => $vat !== '' ? $vat : null,
            'address' => $address,
        ]);

        return $block === [] ? null : $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function formatBuyerAddress(array $payload): ?string
    {
        $addr = (array) ($payload['buyer_national_address'] ?? []);
        $parts = array_filter([
            trim((string) ($addr['building'] ?? '')),
            trim((string) ($addr['street'] ?? '')),
            trim((string) ($addr['district'] ?? '')),
            trim((string) ($addr['city'] ?? '')),
            trim((string) ($addr['postal_code'] ?? '')),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<array<string, mixed>>
     */
    private static function mapLines(array $rawLines, Collection $productSkus): array
    {
        $mapped = [];

        foreach ($rawLines as $line) {
            $qty = InvoiceLineMath::normalizeQuantity((float) ($line['quantity'] ?? 1));
            $unitEx = (float) ($line['unit_price_ex_vat'] ?? 0);
            $discountEx = (float) ($line['discount_ex_vat'] ?? 0);
            $vatRate = (float) ($line['vat_rate'] ?? 15);
            $netEx = InvoiceLineMath::lineNetExVat($unitEx, $qty, $discountEx);
            $lineVat = InvoiceLineMath::lineVatAmount($netEx, $vatRate);
            $lineInc = InvoiceLineMath::lineIncVat($netEx, $vatRate);
            $unitInc = $qty > 0 ? round($lineInc / $qty, 2) : 0.0;

            $productId = $line['product_id'] ?? null;
            $sku = $productId !== null ? (string) ($productSkus->get((int) $productId) ?? '') : '';
            $barcode = $sku !== '' ? $sku : ($productId !== null ? (string) $productId : '');

            $mapped[] = [
                'barcode' => $barcode,
                'sku' => $sku,
                'quantity' => $qty,
                'quantity_display' => InvoiceLineMath::formatQuantity($qty),
                'unit_price_inc' => $unitInc,
                'tax_code' => self::taxCode($vatRate),
                'name_en' => (string) ($line['name'] ?? ''),
                'name_ar' => (string) ($line['name_ar'] ?? $line['name'] ?? ''),
                'line_total_inc' => $lineInc,
                'vat_rate' => $vatRate,
                'line_ex_vat' => $netEx,
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
            'mada' => __('invoices.payment.mada'),
            'card' => __('invoices.payment.card'),
            'cash' => __('invoices.payment.cash'),
            'transfer' => __('invoices.payment.transfer'),
            'credit' => __('invoices.payment.credit'),
            default => __('invoices.payment.cash'),
        };
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

        $legalAddress = $legal['address'] ?? $legal['address_en'] ?? null;
        if (filled($legalAddress) && ! in_array((string) $legalAddress, $lines, true)) {
            $lines[] = (string) $legalAddress;
        }

        if ($lines === [] && filled($content['location_badge_en'] ?? null)) {
            $lines[] = (string) $content['location_badge_en'];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $legal
     * @return list<string>
     */
    private static function branchAddressLinesAr(?Branch $branch, array $legal): array
    {
        $lines = [];

        if ($branch !== null && filled($branch->name_ar ?? null)) {
            $lines[] = $branch->name_ar;
        }

        if (filled($legal['address_ar'] ?? null)) {
            $lines[] = (string) $legal['address_ar'];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $legal
     * @param  array<string, mixed>  $content
     * @return array{phone: ?string, mobile: ?string, mobile_secondary: ?string, fax: ?string, email: ?string}
     */
    private static function contactLines(array $legal, array $content): array
    {
        $legacy = (array) ($content['contact'] ?? []);
        $phone = $legacy['phone'] ?? $content['phone'] ?? $legal['phone'] ?? null;
        $mobile = $legal['mobile'] ?? $legacy['mobile'] ?? null;
        $mobile2 = $legal['mobile_secondary'] ?? null;
        $email = $legacy['email'] ?? $content['email'] ?? $legal['email'] ?? null;

        $mobileOut = filled($mobile) ? (string) $mobile : (filled($phone) ? (string) $phone : null);

        return [
            'phone' => filled($phone) ? (string) $phone : null,
            'mobile' => $mobileOut,
            'mobile_secondary' => filled($mobile2) ? (string) $mobile2 : null,
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