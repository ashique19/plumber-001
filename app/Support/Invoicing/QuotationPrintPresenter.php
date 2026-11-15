<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Quotation;

final class QuotationPrintPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromQuotation(BusinessSetting $business, Quotation $quotation, ?Branch $branch): array
    {
        $payload = $quotation->payload ?? [];
        $legal = $business->settings['legal_entity'] ?? [];
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $signingLines = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $signingLines[] = new InvoiceLineData(
                name: (string) ($row['name'] ?? ''),
                quantity: (float) ($row['quantity'] ?? 1),
                unitPriceExVat: (float) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? 0),
                vatRate: (float) ($row['vat_rate'] ?? 0),
                nameAr: filled($row['name_ar'] ?? null) ? (string) $row['name_ar'] : null,
            );
        }

        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;
        $totals = InvoiceDocumentTotals::compute($signingLines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        $displayLines = [];
        foreach ($signingLines as $line) {
            $displayLines[] = [
                'name' => $line->name,
                'name_ar' => $line->nameAr,
                'qty' => $line->quantity,
                'unit_price' => $line->unitPriceExVat,
                'vat_rate' => $line->vatRate,
                'line_ex_vat' => round($line->quantity * $line->unitPriceExVat, 2),
                'line_vat' => round($line->quantity * $line->unitPriceExVat * ($line->vatRate / 100), 2),
                'line_inc_vat' => round($line->quantity * $line->unitPriceExVat * (1 + $line->vatRate / 100), 2),
            ];
        }

        $na = (array) ($legal['national_address'] ?? []);
        $sellerEn = $legal['name_en'] ?? $business->name;
        $sellerAr = $legal['name_ar'] ?? $business->name;

        $customer = null;
        if (filled($payload['buyer_name'] ?? null)) {
            $customer = [
                'name' => (string) $payload['buyer_name'],
                'phone' => (string) ($payload['buyer_phone'] ?? ''),
                'email' => (string) ($payload['buyer_email'] ?? ''),
                'vat' => (string) ($payload['buyer_vat'] ?? ''),
                'cr' => (string) ($payload['buyer_cr'] ?? ''),
            ];
        }

        return [
            'quotation_number' => $quotation->quotation_number,
            'validity_until' => $quotation->validity_until?->format('Y-m-d'),
            'issue_date' => $quotation->created_at?->timezone(config('app.timezone'))->format('Y-m-d'),
            'seller_en' => $sellerEn,
            'seller_ar' => $sellerAr,
            'address_en' => trim(implode(', ', array_filter([
                $na['building'] ?? null,
                $na['street'] ?? null,
                $na['district'] ?? null,
                $na['city'] ?? null,
            ]))),
            'address_ar' => null,
            'trn' => $legal['vat_number'] ?? null,
            'cr_number' => $legal['cr_number'] ?? null,
            'mobile' => $legal['mobile_primary'] ?? null,
            'mobile_secondary' => $legal['mobile_secondary'] ?? null,
            'branch' => $branch?->name,
            'customer' => $customer,
            'lines' => $displayLines,
            'totals' => $totals,
        ];
    }
}