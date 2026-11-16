<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Support\BusinessProfile;

final class QuotationPrintPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromQuotation(BusinessSetting $business, Quotation $quotation, ?Branch $branch): array
    {
        $payload = $quotation->payload ?? [];
        $legal = $business->settings['legal_entity'] ?? [];
        $theme = $business->settings['theme'] ?? [];
        $na = (array) ($legal['national_address'] ?? []);
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
                discountExVat: (float) ($row['discount_ex_vat'] ?? 0),
                nameAr: filled($row['name_ar'] ?? null) ? (string) $row['name_ar'] : null,
            );
        }

        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;
        $totals = InvoiceDocumentTotals::compute($signingLines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        $displayLines = [];
        foreach ($signingLines as $line) {
            $qty = InvoiceLineMath::normalizeQuantity($line->quantity);
            $netEx = InvoiceLineMath::lineNetExVat($line->unitPriceExVat, $qty, $line->discountExVat);
            $lineVat = InvoiceLineMath::lineVatAmount($netEx, $line->vatRate);
            $lineInc = InvoiceLineMath::lineIncVat($netEx, $line->vatRate);

            $displayLines[] = [
                'quantity' => $qty,
                'quantity_display' => InvoiceLineMath::formatQuantity($qty),
                'unit_price_ex_vat' => round($line->unitPriceExVat, 2),
                'name_en' => $line->name,
                'name_ar' => $line->nameAr,
                'discount_ex_vat' => round($line->discountExVat, 2),
                'taxable_ex_vat' => $netEx,
                'vat_amount' => $lineVat,
                'vat_rate' => $line->vatRate,
                'line_amount_inc_vat' => $lineInc,
            ];
        }

        $issueAt = $quotation->created_at?->timezone(config('app.timezone'));
        $customer = InvoicePrintPresenter::customerFromPayload($payload);
        $cr = $legal['cr_number'] ?? null;
        $unified = $legal['unified_number'] ?? null;
        if ($cr !== null && $unified !== null && (string) $cr === (string) $unified) {
            $unified = null;
        }

        $amountWords = SarAmountInWords::format($totals['total_inc_vat']);

        return [
            'document_kind' => 'quotation',
            'show_qr' => false,
            'is_simplified' => false,
            'quotation_number' => $quotation->quotation_number,
            'document_number' => $quotation->quotation_number,
            'validity_until' => $quotation->validity_until?->format('Y-m-d'),
            'issue_date' => $issueAt?->format('Y-m-d'),
            'issue_datetime' => $issueAt?->format('Y/m/d h:i A'),
            'due_date' => null,
            'supply_date' => null,
            'seller_en' => $legal['name_en'] ?? $business->name,
            'seller_ar' => $legal['name_ar'] ?? $business->name,
            'logo_url' => filled($theme['logo_url'] ?? null) ? $theme['logo_url'] : null,
            'address_en' => filled($legal['address'] ?? $legal['address_en'] ?? null)
                ? (string) ($legal['address'] ?? $legal['address_en'])
                : BusinessProfile::formatNationalAddressEn($na),
            'address_ar' => filled($legal['address_ar'] ?? null)
                ? (string) $legal['address_ar']
                : BusinessProfile::formatNationalAddressAr($na),
            'national_address' => $na,
            'mobile' => $legal['mobile'] ?? $legal['mobile_primary'] ?? null,
            'mobile_secondary' => $legal['mobile_secondary'] ?? null,
            'trn' => $legal['vat_number'] ?? null,
            'cr_number' => $cr,
            'unified_number' => $unified,
            'customer' => $customer,
            'lines' => self::numberTableRows($displayLines),
            'totals' => $totals,
            'net_due_inc_vat' => $totals['net_due_inc_vat'],
            'amount_words_ar' => $amountWords['ar'],
            'amount_words_en' => $amountWords['en'],
            'payment_display_en' => 'Quotation',
            'payment_display_ar' => 'عرض سعر',
            'qr_tlv_base64' => null,
            'branch' => $branch,
        ];
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
}