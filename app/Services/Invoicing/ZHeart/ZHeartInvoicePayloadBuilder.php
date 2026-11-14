<?php

namespace App\Services\Invoicing\ZHeart;

use App\Data\InvoiceLineData;
use App\Rules\SaudiVatNumber;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Support\Invoicing\InvoiceDocumentTotals;
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
            'supply_date' => $issued->format('Y-m-d'),
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
        $vat = preg_replace('/\D/', '', (string) ($request->invoicePayload['buyer_vat'] ?? '')) ?? '';
        $cr = trim((string) ($request->invoicePayload['buyer_cr'] ?? ''));
        $name = trim((string) ($request->invoicePayload['buyer_name'] ?? ''));
        $address = (array) ($request->invoicePayload['buyer_national_address'] ?? []);

        if ($type === 'standard') {
            if ($vat === '') {
                throw new \InvalidArgumentException(__('zheart_api.standard_buyer_vat_required'));
            }

            Validator::make(['vat' => $vat], ['vat' => [new SaudiVatNumber]])->validate();
        }

        $email = trim((string) ($request->invoicePayload['buyer_email'] ?? ''));
        $phone = trim((string) ($request->invoicePayload['buyer_phone'] ?? ''));

        if ($name === '' && $vat === '' && $cr === '' && $address === [] && $email === '' && $phone === '') {
            return [];
        }

        $buyer = array_filter([
            'name' => $name !== '' ? $name : null,
            'vat_number' => $vat !== '' ? $vat : null,
            'cr_number' => $cr !== '' ? $cr : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ]);

        $normalizedAddress = $this->normalizeAddress($address);
        if ($normalizedAddress !== []) {
            $buyer['address'] = $normalizedAddress;
        }

        return $buyer;
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string>
     */
    private function normalizeAddress(array $address): array
    {
        $out = array_filter([
            'building' => trim((string) ($address['building'] ?? '')),
            'street' => trim((string) ($address['street'] ?? '')),
            'district' => trim((string) ($address['district'] ?? '')),
            'city' => trim((string) ($address['city'] ?? '')),
            'postal_code' => preg_replace('/\D/', '', (string) ($address['postal_code'] ?? '')) ?? '',
            'additional' => trim((string) ($address['additional'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        return $out;
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
}
