<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Shared seller/customer/lines/totals for A4 tax and cash-style invoice prints.
 */
final class InvoicePrintPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromPosOrder(
        BusinessSetting $business,
        PosOrder $order,
        Invoice $invoice,
        ?Branch $branch,
    ): array {
        $payload = $invoice->payload ?? [];
        $legal = $business->settings['legal_entity'] ?? [];
        $theme = $business->settings['theme'] ?? [];
        $contentKey = $business->isPlumberTheme() ? 'plumber' : null;
        $content = $contentKey ? data_get($business->settings, "content.{$contentKey}", []) : [];

        $productSkus = self::productSkusForLines($order);
        $displayLines = self::mapDisplayLines($order->line_items ?? [], $productSkus);
        $signingLines = self::signingLinesFromRaw($order->line_items ?? []);

        return self::assemble($business, $invoice, $legal, $theme, $content, $displayLines, $signingLines, $payload, [
            'paid_at' => $order->paid_at?->timezone(config('app.timezone')),
            'customer' => self::customerFromOrder($order, $payload),
            'payment_method' => $order->payment_method instanceof \BackedEnum
                ? $order->payment_method->value
                : (string) ($order->payment_method ?? 'cash'),
            'branch' => $branch,
            'receipt_number' => $order->order_number,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(BusinessSetting $business, Invoice $invoice, ?Branch $branch = null): array
    {
        $payload = $invoice->payload ?? [];
        $legal = $business->settings['legal_entity'] ?? [];
        $theme = $business->settings['theme'] ?? [];
        $contentKey = $business->isPlumberTheme() ? 'plumber' : null;
        $content = $contentKey ? data_get($business->settings, "content.{$contentKey}", []) : [];
        $rawLines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $displayLines = self::mapDisplayLines($rawLines, collect());
        $signingLines = self::signingLinesFromRaw($rawLines);

        return self::assemble($business, $invoice, $legal, $theme, $content, $displayLines, $signingLines, $payload, [
            'paid_at' => $invoice->issued_at?->timezone(config('app.timezone')),
            'customer' => self::customerFromPayload($payload),
            'payment_method' => (string) ($payload['payment_method'] ?? 'cash'),
            'branch' => $branch,
            'receipt_number' => null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $displayLines
     * @param  list<InvoiceLineData>  $signingLines
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function assemble(
        BusinessSetting $business,
        Invoice $invoice,
        array $legal,
        array $theme,
        array $content,
        array $displayLines,
        array $signingLines,
        array $payload,
        array $meta,
    ): array {
        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;
        $totals = InvoiceDocumentTotals::compute($signingLines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        $isSimplified = $invoice->document_type === InvoiceDocumentType::Simplified;
        $amountWordsTotal = SarAmountInWords::format($totals['total_inc_vat']);
        $amountWordsNet = SarAmountInWords::format($totals['net_due_inc_vat']);

        $contact = self::contactLines($legal, $content);
        $na = (array) ($legal['national_address'] ?? []);

        return [
            'seller_en' => $legal['name_en'] ?? $business->name,
            'seller_ar' => $legal['name_ar'] ?? $business->name,
            'logo_url' => filled($theme['logo_url'] ?? null) ? $theme['logo_url'] : null,
            'address_en' => $legal['address'] ?? $legal['address_en'] ?? null,
            'address_ar' => $legal['address_ar'] ?? null,
            'short_national_address' => $legal['short_national_address'] ?? self::formatShortNational($na),
            'national_address' => $na,
            'phone' => $contact['phone'],
            'mobile' => $contact['mobile'],
            'mobile_secondary' => $contact['mobile_secondary'],
            'legal_email' => $legal['email'] ?? $contact['email'],
            'cr_number' => $legal['cr_number'] ?? null,
            'unified_number' => $legal['unified_number'] ?? null,
            'trn' => $legal['vat_number'] ?? null,
            'is_simplified' => $isSimplified,
            'document_type_label_en' => $isSimplified
                ? __('invoices.type.simplified')
                : __('invoices.type.standard'),
            'document_type_label_ar' => $isSimplified ? 'فاتورة مبسطة' : 'فاتورة ضريبية',
            'document_number' => $invoice->document_number,
            'issue_date' => $meta['paid_at']?->format('Y-m-d') ?? $invoice->issued_at?->format('Y-m-d'),
            'issue_time' => $meta['paid_at']?->format('H:i:s') ?? $invoice->issued_at?->format('H:i:s'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'customer' => $meta['customer'],
            'lines' => self::numberTableRows($displayLines),
            'totals' => $totals,
            'total_inc_vat' => $totals['total_inc_vat'],
            'net_due_inc_vat' => $totals['net_due_inc_vat'],
            'amount_words_ar' => $amountWordsTotal['ar'],
            'amount_words_en' => $amountWordsTotal['en'],
            'net_amount_words_ar' => $amountWordsNet['ar'],
            'payment_method' => $meta['payment_method'],
            'payment_display' => __("invoices.payment.{$meta['payment_method']}", [], 'en')
                !== "invoices.payment.{$meta['payment_method']}"
                ? __("invoices.payment.{$meta['payment_method']}")
                : __('invoices.payment.cash'),
            'qr_tlv_base64' => $invoice->qr_tlv_base64,
            'receipt_number' => $meta['receipt_number'],
            'branch' => $meta['branch'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<array<string, mixed>>
     */
    private static function mapDisplayLines(array $rawLines, Collection $productSkus): array
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

            $mapped[] = [
                'quantity' => $qty,
                'quantity_display' => InvoiceLineMath::formatQuantity($qty),
                'unit_price_ex_vat' => round($unitEx, 2),
                'name_en' => (string) ($line['name'] ?? ''),
                'name_ar' => (string) ($line['name_ar'] ?? ''),
                'taxable_ex_vat' => $netEx,
                'vat_amount' => $lineVat,
                'vat_rate' => $vatRate,
                'line_amount_inc_vat' => $lineInc,
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function numberTableRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $row['line_no'] = $i + 1;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<InvoiceLineData>
     */
    private static function signingLinesFromRaw(array $rawLines): array
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
    private static function customerFromPayload(array $payload): ?array
    {
        $address = self::formatBuyerAddress($payload);

        return array_filter([
            'name' => trim((string) ($payload['buyer_name'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['buyer_phone'] ?? '')) ?: null,
            'email' => trim((string) ($payload['buyer_email'] ?? '')) ?: null,
            'vat_number' => trim((string) ($payload['buyer_vat'] ?? '')) ?: null,
            'address' => $address,
        ]) ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function customerFromOrder(PosOrder $order, array $payload): ?array
    {
        $customer = $order->relationLoaded('customer') ? $order->customer : null;
        $base = self::customerFromPayload($payload) ?? [];

        if ($customer !== null) {
            $base['name'] ??= filled($customer->name) ? $customer->name : null;
            $base['phone'] ??= filled($customer->phone) ? $customer->phone : null;
            $base['email'] ??= filled($customer->email) ? $customer->email : null;
            $vat = preg_replace('/\D/', '', (string) data_get($customer->profile, 'vat_number', '')) ?? '';
            $base['vat_number'] ??= $vat !== '' ? $vat : null;
        }

        return $base === [] ? null : array_filter($base);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function formatBuyerAddress(array $payload): ?string
    {
        $addr = (array) ($payload['buyer_national_address'] ?? []);

        return self::formatShortNational($addr) ?: null;
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private static function formatShortNational(array $addr): ?string
    {
        $parts = array_filter([
            trim((string) ($addr['building'] ?? '')),
            trim((string) ($addr['street'] ?? '')),
            trim((string) ($addr['district'] ?? '')),
            trim((string) ($addr['city'] ?? '')),
            trim((string) ($addr['postal_code'] ?? '')),
            trim((string) ($addr['additional'] ?? '')),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $legal
     * @param  array<string, mixed>  $content
     * @return array{phone: ?string, mobile: ?string, mobile_secondary: ?string, email: ?string}
     */
    private static function contactLines(array $legal, array $content): array
    {
        $legacy = (array) ($content['contact'] ?? []);
        $phone = $legacy['phone'] ?? $content['phone'] ?? $legal['phone'] ?? null;
        $mobile = $legal['mobile'] ?? $legacy['mobile'] ?? null;
        $mobile2 = $legal['mobile_secondary'] ?? null;
        $email = $legal['email'] ?? $legacy['email'] ?? $content['email'] ?? null;
        $mobileOut = filled($mobile) ? (string) $mobile : (filled($phone) ? (string) $phone : null);

        return [
            'phone' => filled($phone) ? (string) $phone : null,
            'mobile' => $mobileOut,
            'mobile_secondary' => filled($mobile2) ? (string) $mobile2 : null,
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