<?php

namespace App\Support\Invoicing;

use App\Livewire\Invoices\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Support\Hub\HubInvoiceRowActions;
use Illuminate\Http\Request;

final class IssueInvoicePrefill
{
    public const SESSION_KEY = 'issue_invoice_prefill';

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice): array
    {
        $payload = $invoice->payload ?? [];
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $mappedLines = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mappedLines[] = [
                'name' => (string) ($row['name'] ?? ''),
                'name_ar' => (string) ($row['name_ar'] ?? ''),
                'quantity' => (string) ($row['quantity'] ?? '1'),
                'unit_price' => (string) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? ''),
                'vat_rate' => (string) ($row['vat_rate'] ?? '15'),
                'product_id' => $row['product_id'] ?? null,
                'unit_label' => (string) ($row['unit_label'] ?? ''),
            ];
        }

        $mode = $invoice->document_type->value === 'standard' ? 'standard' : 'simplified';

        $prefill = [
            'source_invoice_id' => $invoice->id,
            'mode' => $mode,
            'lines' => $mappedLines,
            'invoiceDiscountExVat' => (string) ($payload['invoice_discount_ex_vat'] ?? '0'),
            'retentionPercent' => isset($payload['retention_percent']) ? (string) $payload['retention_percent'] : '',
            'retentionFixed' => isset($payload['retention_fixed']) ? (string) $payload['retention_fixed'] : '',
            'buyerPhone' => (string) ($payload['buyer_phone'] ?? ''),
            'buyerEmail' => (string) ($payload['buyer_email'] ?? ''),
            'buyerName' => (string) ($payload['buyer_name'] ?? ''),
            'buyerVat' => (string) ($payload['buyer_vat'] ?? ''),
            'buyerCr' => (string) ($payload['buyer_cr'] ?? ''),
            'buyerNameOptional' => (string) ($payload['buyer_name'] ?? ''),
            'buyerCountry' => (string) ($payload['buyer_country'] ?? 'SA'),
            'dueDate' => (string) ($payload['buyer_due_date'] ?? ''),
        ];

        $na = (array) ($payload['buyer_national_address'] ?? []);
        $prefill['addrBuilding'] = (string) ($na['building'] ?? '');
        $prefill['addrStreet'] = (string) ($na['street'] ?? '');
        $prefill['addrDistrict'] = (string) ($na['district'] ?? '');
        $prefill['addrCity'] = (string) ($na['city'] ?? 'Riyadh');
        $prefill['addrPostal'] = (string) ($na['postal_code'] ?? '');
        $prefill['addrPostBox'] = (string) ($na['post_box'] ?? '');
        $prefill['addrAdditional'] = (string) ($na['additional'] ?? '');

        return $prefill;
    }

    public static function fromQuotation(Quotation $quotation): array
    {
        $payload = $quotation->payload ?? [];
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $mappedLines = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mappedLines[] = [
                'name' => (string) ($row['name'] ?? ''),
                'name_ar' => (string) ($row['name_ar'] ?? ''),
                'quantity' => (string) ($row['quantity'] ?? '1'),
                'unit_price' => (string) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? ''),
                'vat_rate' => (string) ($row['vat_rate'] ?? '15'),
                'product_id' => $row['product_id'] ?? null,
                'unit_label' => (string) ($row['unit_label'] ?? ''),
            ];
        }

        $prefill = [
            'quotation_id' => $quotation->id,
            'lines' => $mappedLines,
            'invoiceDiscountExVat' => (string) ($payload['invoice_discount_ex_vat'] ?? '0'),
            'retentionPercent' => isset($payload['retention_percent']) ? (string) $payload['retention_percent'] : '',
            'retentionFixed' => isset($payload['retention_fixed']) ? (string) $payload['retention_fixed'] : '',
            'buyerPhone' => (string) ($payload['buyer_phone'] ?? ''),
            'buyerEmail' => (string) ($payload['buyer_email'] ?? ''),
            'buyerName' => (string) ($payload['buyer_name'] ?? ''),
            'buyerVat' => (string) ($payload['buyer_vat'] ?? ''),
            'buyerCr' => (string) ($payload['buyer_cr'] ?? ''),
            'buyerNameOptional' => (string) ($payload['buyer_name'] ?? ''),
            'buyerCountry' => (string) ($payload['buyer_country'] ?? 'SA'),
            'dueDate' => (string) ($payload['buyer_due_date'] ?? ''),
        ];

        $na = (array) ($payload['buyer_national_address'] ?? []);
        $prefill['addrBuilding'] = (string) ($na['building'] ?? '');
        $prefill['addrStreet'] = (string) ($na['street'] ?? '');
        $prefill['addrDistrict'] = (string) ($na['district'] ?? '');
        $prefill['addrCity'] = (string) ($na['city'] ?? 'Riyadh');
        $prefill['addrPostal'] = (string) ($na['postal_code'] ?? '');
        $prefill['addrAdditional'] = (string) ($na['additional'] ?? '');

        return $prefill;
    }

    public static function stashFromQuotation(Quotation $quotation): void
    {
        session([self::SESSION_KEY => self::fromQuotation($quotation)]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function applyDraftToComponent(IssueInvoice $component, Invoice $draft): void
    {
        $prefill = self::fromInvoice($draft);
        $component->lines = $prefill['lines'] ?? $component->lines;
        $component->invoiceDiscountExVat = $prefill['invoiceDiscountExVat'] ?? $component->invoiceDiscountExVat;
        $component->retentionPercent = $prefill['retentionPercent'] ?? $component->retentionPercent;
        $component->retentionFixed = $prefill['retentionFixed'] ?? $component->retentionFixed;
        $component->buyerPhone = $prefill['buyerPhone'] ?? $component->buyerPhone;
        $component->buyerEmail = $prefill['buyerEmail'] ?? $component->buyerEmail;
        $component->buyerName = $prefill['buyerName'] ?? $component->buyerName;
        $component->buyerVat = $prefill['buyerVat'] ?? $component->buyerVat;
        $component->buyerCr = $prefill['buyerCr'] ?? $component->buyerCr;
        $component->buyerNameOptional = $prefill['buyerNameOptional'] ?? $component->buyerNameOptional;
        $component->buyerCountry = $prefill['buyerCountry'] ?? $component->buyerCountry;
        if (filled($prefill['dueDate'] ?? null)) {
            $component->dueDate = (string) $prefill['dueDate'];
        }
        $component->addrBuilding = $prefill['addrBuilding'] ?? $component->addrBuilding;
        $component->addrStreet = $prefill['addrStreet'] ?? $component->addrStreet;
        $component->addrDistrict = $prefill['addrDistrict'] ?? $component->addrDistrict;
        $component->addrCity = $prefill['addrCity'] ?? $component->addrCity;
        $component->addrPostal = $prefill['addrPostal'] ?? $component->addrPostal;
        $component->addrPostBox = $prefill['addrPostBox'] ?? $component->addrPostBox;
        $component->addrAdditional = $prefill['addrAdditional'] ?? $component->addrAdditional;
    }

    public static function applyIfPresent(IssueInvoice $component, string $mode, ?Request $request = null): void
    {
        $prefill = session(self::SESSION_KEY);

        if ($request !== null) {
            $fromQuotation = $request->query('from_quotation');
            if ($fromQuotation !== null && $prefill === null) {
                $quotation = Quotation::query()->find((int) $fromQuotation);
                if ($quotation !== null) {
                    $prefill = self::fromQuotation($quotation);
                    session([self::SESSION_KEY => $prefill]);
                }
            }

            $duplicateFrom = $request->query('duplicate_from');
            if ($duplicateFrom !== null && $prefill === null) {
                $source = Invoice::query()->find((int) $duplicateFrom);
                if ($source !== null && $source->isIssued()) {
                    $prefill = self::fromInvoice($source);
                    session([self::SESSION_KEY => $prefill]);
                }
            }

            $draftId = $request->query('draft');
            if ($draftId !== null) {
                $draft = Invoice::query()
                    ->whereKey((int) $draftId)
                    ->where('status', InvoiceStatus::Draft)
                    ->first();
                if ($draft !== null && HubInvoiceRowActions::canEdit($draft)) {
                    self::applyDraftToComponent($component, $draft);

                    return;
                }
            }
        }

        if (! is_array($prefill)) {
            return;
        }

        if (! isset($prefill['quotation_id']) && ($prefill['mode'] ?? $mode) !== $mode) {
            return;
        }

        $component->lines = $prefill['lines'] ?? $component->lines;
        $component->invoiceDiscountExVat = $prefill['invoiceDiscountExVat'] ?? $component->invoiceDiscountExVat;
        $component->retentionPercent = $prefill['retentionPercent'] ?? $component->retentionPercent;
        $component->retentionFixed = $prefill['retentionFixed'] ?? $component->retentionFixed;
        $component->buyerPhone = $prefill['buyerPhone'] ?? $component->buyerPhone;
        $component->buyerEmail = $prefill['buyerEmail'] ?? $component->buyerEmail;
        $component->buyerName = $prefill['buyerName'] ?? $component->buyerName;
        $component->buyerVat = $prefill['buyerVat'] ?? $component->buyerVat;
        $component->buyerCr = $prefill['buyerCr'] ?? $component->buyerCr;
        $component->buyerNameOptional = $prefill['buyerNameOptional'] ?? $component->buyerNameOptional;
        $component->buyerCountry = $prefill['buyerCountry'] ?? $component->buyerCountry;
        if (filled($prefill['dueDate'] ?? null)) {
            $component->dueDate = (string) $prefill['dueDate'];
        }
        $component->addrBuilding = $prefill['addrBuilding'] ?? $component->addrBuilding;
        $component->addrStreet = $prefill['addrStreet'] ?? $component->addrStreet;
        $component->addrDistrict = $prefill['addrDistrict'] ?? $component->addrDistrict;
        $component->addrCity = $prefill['addrCity'] ?? $component->addrCity;
        $component->addrPostal = $prefill['addrPostal'] ?? $component->addrPostal;
        $component->addrAdditional = $prefill['addrAdditional'] ?? $component->addrAdditional;
    }
}