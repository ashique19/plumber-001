<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Product;
use App\Support\BusinessProfile;
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

        $issueAt = $meta['paid_at'] ?? $invoice->issued_at;
        $issueDate = $issueAt?->format('Y-m-d');
        $issueTime = $issueAt?->format('H:i:s');
        $payloadSupply = trim((string) (data_get($invoice->payload, 'supply_date') ?? ''));
        $supplyDate = $payloadSupply !== '' ? $payloadSupply : $issueDate;

        $paymentMethod = (string) ($meta['payment_method'] ?? 'cash');
        $paymentLabels = self::paymentLabels($paymentMethod);
        $paymentTermsCode = \App\Support\Invoicing\DocumentPaymentTerms::normalizeCode(
            (string) ($payload['payment_terms'] ?? ''),
            max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30)),
        );
        $paymentTermsEn = \App\Support\Invoicing\DocumentPaymentTerms::label($paymentTermsCode, 'en');
        $paymentTermsAr = \App\Support\Invoicing\DocumentPaymentTerms::label($paymentTermsCode, 'ar');

        $trn = $legal['vat_number'] ?? null;
        $cr = $legal['cr_number'] ?? null;
        $unified = $legal['unified_number'] ?? null;
        if ($cr !== null && $unified !== null && (string) $cr === (string) $unified) {
            $unified = null;
        }

        $qrSigned = filled($invoice->qr_tlv_base64) ? (string) $invoice->qr_tlv_base64 : null;
        $qrFallback = false;
        $cleared = InvoicePrintClearance::isZatcaCleared($invoice);
        $printWatermark = false;
        $officialPrintBlocked = false;
        $draftPreview = $invoice->status === InvoiceStatus::Draft;

        if ($draftPreview) {
            $qr = null;
            $qrFallback = false;
            $printWatermark = false;
            $officialPrintBlocked = true;
        } elseif ($isSimplified) {
            $qr = $qrSigned;
            if ($qr === null) {
                $timestamp = $issueAt
                    ? $issueAt->copy()->timezone('Asia/Riyadh')->format('Y-m-d\TH:i:sP')
                    : now('Asia/Riyadh')->format('Y-m-d\TH:i:sP');
                $sellerForQr = (string) ($legal['name_ar'] ?? $legal['name_en'] ?? $business->name);
                $qr = ZatcaPhase1QrEncoder::encodeBase64(
                    sellerName: $sellerForQr,
                    vatNumber: (string) ($trn ?? ''),
                    timestampIso8601: $timestamp,
                    invoiceTotalIncVat: number_format($totals['total_inc_vat'], 2, '.', ''),
                    vatTotal: number_format($totals['total_vat'], 2, '.', ''),
                );
                $qrFallback = true;
            }
        } else {
            if ($cleared && $qrSigned !== null) {
                $qr = $qrSigned;
            } else {
                $qr = null;
                $printWatermark = true;
                $officialPrintBlocked = true;
            }
        }

        return [
            'seller_en' => $legal['name_en'] ?? $business->name,
            'seller_ar' => $legal['name_ar'] ?? $business->name,
            'logo_url' => filled($theme['logo_url'] ?? null) ? $theme['logo_url'] : null,
            'address_en' => filled($legal['address'] ?? $legal['address_en'] ?? null)
                ? (string) ($legal['address'] ?? $legal['address_en'])
                : BusinessProfile::formatNationalAddressEn($na),
            'address_ar' => filled($legal['address_ar'] ?? null)
                ? (string) $legal['address_ar']
                : BusinessProfile::formatNationalAddressAr($na),
            'short_national_address' => $legal['short_national_address'] ?? self::formatShortNational($na),
            'national_address' => $na,
            'phone' => $contact['phone'],
            'mobile' => $contact['mobile'],
            'mobile_secondary' => $contact['mobile_secondary'],
            'legal_email' => $legal['email'] ?? $contact['email'],
            'cr_number' => $cr,
            'unified_number' => $unified,
            'trn' => $trn,
            'is_simplified' => $isSimplified,
            'document_type_label_en' => $isSimplified ? 'Simplified' : 'Standard',
            'document_type_label_ar' => $isSimplified ? 'فاتورة مبسطة' : 'فاتورة ضريبية',
            'document_number' => $invoice->document_number,
            'issue_date' => $issueDate,
            'issue_time' => $issueTime,
            'supply_date' => $supplyDate,
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'customer' => $meta['customer'],
            'lines' => self::numberTableRows($displayLines),
            'totals' => $totals,
            'total_inc_vat' => $totals['total_inc_vat'],
            'net_due_inc_vat' => $totals['net_due_inc_vat'],
            'amount_words_ar' => $amountWordsTotal['ar'],
            'amount_words_en' => $amountWordsTotal['en'],
            'net_amount_words_ar' => $amountWordsNet['ar'],
            'payment_method' => $paymentMethod,
            'payment_display_en' => $paymentLabels['en'],
            'payment_display_ar' => $paymentLabels['ar'],
            'payment_terms_en' => $paymentTermsEn,
            'payment_terms_ar' => $paymentTermsAr,
            'qr_tlv_base64' => $qr,
            'qr_phase1_fallback' => $qrFallback,
            'print_watermark' => $printWatermark,
            'draft_preview' => $draftPreview,
            'official_print_blocked' => $officialPrintBlocked,
            'receipt_number' => $meta['receipt_number'],
            'branch' => $meta['branch'],
            'document_kind' => 'invoice',
            'show_qr' => ! $draftPreview && (($isSimplified && $qr !== null) || (! $isSimplified && $cleared && $qrSigned !== null)),
            'issue_datetime' => $issueAt?->timezone(config('app.timezone'))->format('Y/m/d h:i A'),
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

            $nameEn = (string) ($line['name'] ?? '');
            $nameAr = trim((string) ($line['name_ar'] ?? ''));

            $mapped[] = [
                'quantity' => $qty,
                'quantity_display' => InvoiceLineMath::formatQuantity($qty),
                'unit_price_ex_vat' => round($unitEx, 2),
                'name_en' => $nameEn,
                'name_ar' => $nameAr !== '' ? $nameAr : null,
                'discount_ex_vat' => round($discountEx, 2),
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
    public static function customerFromPayload(array $payload): ?array
    {
        $na = (array) ($payload['buyer_national_address'] ?? []);
        $address = self::formatBuyerAddress($payload);
        $countryCode = strtoupper(trim((string) ($na['country'] ?? 'SA'))) ?: 'SA';
        $countryName = CountryPrintName::english($countryCode);
        if ($address !== null && $countryName !== '' && ! str_contains($address, $countryName)) {
            $address = $address.', '.$countryName;
        }

        $vat = preg_replace('/\D/', '', (string) ($payload['buyer_vat'] ?? '')) ?? '';
        $nameEn = trim((string) ($payload['buyer_name_en'] ?? $payload['buyer_name'] ?? ''));
        $nameAr = trim((string) ($payload['buyer_name_ar'] ?? ''));
        $displayName = $nameEn !== '' ? $nameEn : ($nameAr !== '' ? $nameAr : '');

        $altType = strtoupper(trim((string) ($payload['buyer_alt_id_type'] ?? '')));
        $altNumber = trim((string) ($payload['buyer_alt_id_number'] ?? ''));
        $cr = trim((string) ($payload['buyer_cr'] ?? ''));
        if ($altType === 'CRN' && $altNumber !== '') {
            $cr = $altNumber;
        } elseif ($cr === '' && $altType === 'CRN') {
            $cr = $altNumber;
        }

        return array_filter([
            'name' => $displayName !== '' ? $displayName : null,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'name_ar' => $nameAr !== '' ? $nameAr : null,
            'phone' => trim((string) ($payload['buyer_phone'] ?? '')) ?: null,
            'email' => trim((string) ($payload['buyer_email'] ?? '')) ?: null,
            'vat_number' => $vat !== '' ? $vat : null,
            'cr_number' => $cr !== '' ? $cr : null,
            'address' => $address,
            'address_print' => $address,
            'address_parts' => $na !== [] ? $na : null,
            'country_code' => $countryCode,
            'city' => trim((string) ($na['city'] ?? '')) ?: null,
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
        $fromNational = BusinessProfile::formatNationalAddressEn($addr);
        if ($fromNational !== null && $fromNational !== '') {
            return $fromNational;
        }

        $line = trim((string) ($payload['buyer_address'] ?? $payload['buyer_address_line'] ?? ''));

        return self::formatShortNational($addr) ?: ($line !== '' ? $line : null);
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private static function formatShortNational(array $addr): ?string
    {
        return BusinessProfile::formatNationalAddressEn($addr)
            ?? (filled($addr['short_national'] ?? $addr['short_national_address'] ?? null)
                ? (string) ($addr['short_national'] ?? $addr['short_national_address'])
                : null);
    }

    /**
     * @return array{en: string, ar: string}
     */
    private static function paymentLabels(string $method): array
    {
        return match ($method) {
            'card' => ['en' => 'Card', 'ar' => 'بطاقة'],
            'mada' => ['en' => 'Mada', 'ar' => 'مدى'],
            'transfer' => ['en' => 'Bank transfer', 'ar' => 'تحويل بنكي'],
            'credit' => ['en' => 'Credit', 'ar' => 'آجل'],
            default => ['en' => 'Cash', 'ar' => 'نقداً'],
        };
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