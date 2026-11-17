<?php

namespace App\Services\Invoicing\ZHeart;

use App\Data\InvoiceLineData;
use App\Rules\SaudiVatNumber;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Invoicing\ZHeartBuyerAddressMapper;
use Illuminate\Support\Facades\Validator;

final class ZHeartInvoicePayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(InvoiceSigningRequest $request): array
    {
        $issued = $request->issueDateTimeUtc->copy()->timezone('Asia/Riyadh');
        $legal = $request->business->settings['legal_entity'] ?? [];

        $type = $request->invoiceCategory === 'simplified' ? 'simplified' : 'standard';
        $documentType = match ($request->documentType->value) {
            'credit_note' => 'credit',
            'debit_note' => 'debit',
            default => 'invoice',
        };

        $payload = [
            'uuid' => $request->invoiceUuid,
            'invoice_number' => $request->documentNumber,
            'issue_date' => $issued->format('Y-m-d'),
            'issue_time' => $issued->format('H:i:s'),
            'supply_date' => self::resolveSupplyDate($request->invoicePayload, $issued),
            'currency' => 'SAR',
            'type' => $type,
            'document_type' => $documentType,
            'lines' => $this->mapLines($request->lines),
            'totals' => $this->totals($request->lines, $request->documentDiscountExVat),
        ];

        if ($request->documentDiscountExVat > 0) {
            $payload['document_discount_ex_vat'] = round($request->documentDiscountExVat, 2);
        }

        if (in_array($documentType, ['credit', 'debit'], true)) {
            if ($request->referencedInvoiceNumber) {
                $payload['billing_reference'] = $request->referencedInvoiceNumber;
            }
            if (filled($request->referencedInvoiceUuid)) {
                $payload['billing_reference_uuid'] = $request->referencedInvoiceUuid;
            }
            if (filled($request->noteReason)) {
                $payload['payment_means_note'] = $request->noteReason;
            }
        }

        $buyerBlock = $this->buyerBlock($request, $type);
        if ($buyerBlock !== []) {
            $payload['buyer'] = $buyerBlock;
        }

        if (config('zheart.allow_seller_override', false)) {
            $payload['seller'] = array_filter([
                'name' => $legal['name_en'] ?? $request->business->name,
                'vat_number' => $legal['vat_number'] ?? null,
            ]);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerBlock(InvoiceSigningRequest $request, string $type): array
    {
        $payload = $request->invoicePayload;
        $vatRegistered = (bool) ($payload['buyer_vat_registered'] ?? false);
        $vat = preg_replace('/\D/', '', (string) ($payload['buyer_vat'] ?? '')) ?? '';
        $altType = strtoupper(trim((string) ($payload['buyer_alt_id_type'] ?? '')));
        $altNumber = trim((string) ($payload['buyer_alt_id_number'] ?? ''));
        $cr = trim((string) ($payload['buyer_cr'] ?? ''));
        if ($altType === 'CRN' && $altNumber !== '') {
            $cr = $altNumber;
        }

        if (! $vatRegistered && $vat !== '') {
            $vatRegistered = true;
        }
        if (! $vatRegistered && $altType === '' && $cr !== '') {
            $altType = 'CRN';
            $altNumber = $cr;
        }

        $nameEn = trim((string) ($payload['buyer_name_en'] ?? $payload['buyer_name'] ?? ''));
        $nameAr = trim((string) ($payload['buyer_name_ar'] ?? ''));
        $name = $nameEn !== '' ? $nameEn : $nameAr;
        $address = (array) ($payload['buyer_national_address'] ?? []);

        if ($type === 'standard') {
            if ($vatRegistered) {
                if ($vat === '') {
                    throw new \InvalidArgumentException(__('invoices.errors.buyer_id_required'));
                }
                Validator::make(['vat' => $vat], ['vat' => [new SaudiVatNumber]])->validate();
            } elseif ($altType === '' || $altNumber === '') {
                throw new \InvalidArgumentException(__('invoices.errors.buyer_id_required'));
            }
        }

        $email = trim((string) ($payload['buyer_email'] ?? ''));
        $phone = trim((string) ($payload['buyer_phone'] ?? ''));

        if ($name === '' && $vat === '' && $altNumber === '' && $address === [] && $email === '' && $phone === '') {
            return [];
        }

        $country = strtoupper(trim((string) ($payload['buyer_country'] ?? 'SA'))) ?: 'SA';

        $buyer = array_filter([
            'name' => $name !== '' ? $name : null,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'name_ar' => $nameAr !== '' ? $nameAr : null,
            'vat_number' => $vatRegistered && $vat !== '' ? $vat : null,
            'cr_number' => $cr !== '' ? $cr : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'country_code' => $country,
        ]);

        if (! $vatRegistered && $altType !== '' && $altNumber !== '') {
            $buyer['party_identification'] = [
                'scheme_id' => $altType,
                'id' => $altNumber,
            ];
        }

        $addressCountry = ZHeartBuyerAddressMapper::countryCode($address, $country);
        $includeAddress = $type === 'standard'
            || ZHeartBuyerAddressMapper::hasCompleteSaudiNationalAddress($address);

        if ($includeAddress) {
            $normalizedAddress = ZHeartBuyerAddressMapper::toZHeart($address, $addressCountry, true);

            if ($type === 'standard' && ! isset($normalizedAddress['country'])) {
                $normalizedAddress['country'] = 'SA';
            }

            if ($normalizedAddress !== [] || $type === 'standard') {
                if ($type === 'standard' && $normalizedAddress === []) {
                    $normalizedAddress = ['country' => 'SA'];
                }
                $buyer['address'] = $normalizedAddress;
            }
        }

        return $buyer;
    }

    /**
     * @param  list<InvoiceLineData>  $lines
     * @return list<array<string, mixed>>
     */
    private function mapLines(array $lines): array
    {
        return array_map(function (InvoiceLineData $line) {
            $row = [
                'name' => $line->name,
                'quantity' => round($line->quantity, 3),
                'unit_price' => round($line->unitPriceExVat, 2),
                'vat_rate' => $line->vatRate,
            ];

            if (filled($line->nameAr)) {
                $row['name_ar'] = $line->nameAr;
            }

            if ($line->discountExVat > 0) {
                $row['discount'] = round($line->discountExVat, 2);
            }

            if (filled($line->unitCode)) {
                $row['unit_code'] = $line->unitCode;
            }

            if (filled($line->vatZatcaCategory)) {
                $row['vat_category'] = $line->vatZatcaCategory;
            }

            return $row;
        }, $lines);
    }

    /**
     * @param  list<InvoiceLineData>  $lines
     * @return array{tax_exclusive: float, tax: float, tax_inclusive: float}
     */
    private function totals(array $lines, float $documentDiscountExVat = 0.0): array
    {
        $computed = InvoiceDocumentTotals::compute($lines, $documentDiscountExVat);

        return [
            'tax_exclusive' => $computed['subtotal_ex_vat'] - $computed['invoice_discount_ex_vat'],
            'tax' => $computed['total_vat'],
            'tax_inclusive' => $computed['total_inc_vat'],
        ];
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    private static function resolveSupplyDate(array $invoicePayload, \Illuminate\Support\Carbon $issued): string
    {
        $supply = trim((string) ($invoicePayload['supply_date'] ?? ''));

        return $supply !== '' ? $supply : $issued->format('Y-m-d');
    }
}
