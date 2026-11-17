<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Rules\SaudiVatNumber;
use Illuminate\Support\Facades\Validator;

/**
 * Local validation before calling Z-Heart for standard (clearance) invoices.
 */
final class StandardInvoiceZatcaPayloadPrecheck
{
    /**
     * @return list<string> Human-readable missing/invalid field lines (localized).
     */
    public static function missingFields(Invoice $invoice): array
    {
        if (! self::isStandardCategory($invoice)) {
            return [];
        }

        $payload = $invoice->payload ?? [];

        return self::validatePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function validatePayload(array $payload): array
    {
        $issues = [];

        $vat = preg_replace('/\D/', '', (string) ($payload['buyer_vat'] ?? '')) ?? '';
        $cr = trim((string) ($payload['buyer_cr'] ?? ''));

        if ($vat === '' && $cr === '') {
            $issues[] = __('invoices.zatca_precheck.buyer_vat_or_cr');
        } else {
            if ($vat === '') {
                $issues[] = __('invoices.zatca_precheck.buyer_vat');
            } elseif (! Validator::make(['vat' => $vat], ['vat' => [new SaudiVatNumber]])->passes()) {
                $issues[] = __('invoices.zatca_precheck.buyer_vat_invalid');
            }
            if ($cr === '') {
                $issues[] = __('invoices.zatca_precheck.buyer_cr');
            } elseif (! preg_match('/^\d{1,10}$/', $cr)) {
                $issues[] = __('invoices.zatca_precheck.buyer_cr_invalid');
            }
        }

        $address = (array) ($payload['buyer_national_address'] ?? []);

        if (trim((string) ($address['street'] ?? '')) === '') {
            $issues[] = __('invoices.zatca_precheck.buyer_street');
        }
        if (trim((string) ($address['building'] ?? '')) === '') {
            $issues[] = __('invoices.zatca_precheck.buyer_building');
        }
        if (trim((string) ($address['district'] ?? '')) === '') {
            $issues[] = __('invoices.zatca_precheck.buyer_district');
        }
        if (trim((string) ($address['city'] ?? '')) === '') {
            $issues[] = __('invoices.zatca_precheck.buyer_city');
        }

        $postal = ZHeartBuyerAddressMapper::postalDigits($address);
        if ($postal === '' || ! preg_match('/^\d{5}$/', $postal)) {
            $issues[] = __('invoices.zatca_precheck.buyer_postal');
        }

        $buyerCountry = strtoupper(trim((string) ($payload['buyer_country'] ?? '')));
        $addressCountry = ZHeartBuyerAddressMapper::countryCode($address, $buyerCountry !== '' ? $buyerCountry : 'SA');
        $resolvedCountry = $buyerCountry !== '' ? $buyerCountry : ($addressCountry !== '' ? $addressCountry : 'SA');
        if ($resolvedCountry !== 'SA') {
            $issues[] = __('invoices.zatca_precheck.buyer_country_sa');
        }

        $supply = trim((string) ($payload['supply_date'] ?? ''));
        if ($supply === '') {
            $issues[] = __('invoices.zatca_precheck.supply_date');
        }

        return $issues;
    }

    private static function isStandardCategory(Invoice $invoice): bool
    {
        $category = (string) (data_get($invoice->payload, 'invoice_category') ?? '');

        if ($category === 'standard') {
            return true;
        }

        return $invoice->document_type === InvoiceDocumentType::Standard;
    }
}