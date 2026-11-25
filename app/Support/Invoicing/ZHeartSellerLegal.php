<?php

namespace App\Support\Invoicing;

use App\Models\BusinessSetting;
use App\Models\Invoice;

/**
 * Z-Heart owns the seller identity. Invoices store the seller block Z-Heart returned at
 * signing; prints overlay it on the local legal_entity shape so every template reads one
 * array. Local legal settings remain only as a display fallback for older invoices, and
 * for contact lines (phone, mobile, email), which are not part of the seller identity.
 */
final class ZHeartSellerLegal
{
    /** @return array<string, mixed> */
    public static function forInvoice(BusinessSetting $business, ?Invoice $invoice): array
    {
        $local = (array) ($business->settings['legal_entity'] ?? []);
        $seller = $invoice !== null && is_array($invoice->zheart_seller) ? $invoice->zheart_seller : null;

        return self::overlay($local, $seller);
    }

    /**
     * Quotations are not signed: use the seller from the latest Z-Heart invoice, if any.
     *
     * @return array<string, mixed>
     */
    public static function forQuotation(BusinessSetting $business): array
    {
        return self::overlay((array) ($business->settings['legal_entity'] ?? []), self::latestSeller());
    }

    /** @return array<string, mixed>|null */
    public static function latestSeller(): ?array
    {
        $invoice = Invoice::query()->whereNotNull('zheart_seller')->latest('id')->first(['id', 'zheart_seller']);

        return $invoice !== null && is_array($invoice->zheart_seller) && filled($invoice->zheart_seller['vat_number'] ?? null)
            ? $invoice->zheart_seller
            : null;
    }

    /**
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>|null  $seller
     * @return array<string, mixed>
     */
    public static function overlay(array $local, ?array $seller): array
    {
        if ($seller === null || blank($seller['vat_number'] ?? null)) {
            $local['zheart_source'] = false;

            return $local;
        }

        $address = is_array($seller['address'] ?? null) ? $seller['address'] : [];
        $scheme = self::str($seller['id_scheme'] ?? '');
        $id = self::str($seller['id'] ?? '');
        $nameEn = self::str($seller['name_en'] ?? '') ?: self::str($seller['name'] ?? '');
        $nameAr = self::str($seller['name_ar'] ?? '') ?: $nameEn;

        $localNa = (array) ($local['national_address'] ?? []);
        $arabic = [];
        foreach (['street', 'district', 'city'] as $part) {
            $zh = self::str($address[$part] ?? '');
            if ($zh !== '' && filled($localNa[$part.'_ar'] ?? null)
                && mb_strtolower(self::str($localNa[$part] ?? '')) === mb_strtolower($zh)) {
                $arabic[$part.'_ar'] = self::str($localNa[$part.'_ar']);
            }
        }

        $legal = $local;
        foreach (['address', 'address_en', 'address_ar', 'short_national_address', 'unified_number'] as $key) {
            unset($legal[$key]);
        }

        return array_merge($legal, [
            'zheart_source' => true,
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'vat_number' => self::str($seller['vat_number'] ?? ''),
            'cr_number' => self::str($seller['cr_number'] ?? '') ?: ($scheme === 'CRN' ? $id : null),
            'unified_number' => $scheme === '700' ? $id : null,
            'seller_id_scheme' => $scheme,
            'seller_id' => $id,
            'branch_name' => self::str($seller['branch'] ?? '') ?: null,
            'national_address' => array_filter([
                'building' => self::str($address['building'] ?? ''),
                'street' => self::str($address['street'] ?? ''),
                'district' => self::str($address['district'] ?? ''),
                'city' => self::str($address['city'] ?? ''),
                'postal_code' => self::str($address['postal_code'] ?? $address['postal'] ?? ''),
                'additional' => self::str($address['additional'] ?? ''),
                'country' => self::str($address['country'] ?? 'SA'),
            ], fn (string $v): bool => $v !== '') + $arabic + ['ar_fallback' => false],
        ]);
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
