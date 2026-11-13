<?php

namespace App\Services\Invoicing\ZHeart;

use App\Data\InvoiceLineData;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;

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
            'totals' => $this->totals($request->lines),
        ];

        if (in_array($documentType, ['credit', 'debit'], true) && $request->referencedInvoiceNumber) {
            $payload['billing_reference'] = $request->referencedInvoiceNumber;
        }

        if ($type === 'standard') {
            $buyerVat = (string) ($request->invoicePayload['buyer_vat'] ?? '');
            $payload['buyer'] = array_filter([
                'name' => $request->invoicePayload['buyer_name'] ?? null,
                'vat_number' => $buyerVat !== '' ? $buyerVat : null,
            ]);
        } elseif (filled($request->invoicePayload['buyer_name'] ?? null)) {
            $payload['buyer'] = [
                'name' => (string) $request->invoicePayload['buyer_name'],
            ];
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
     * @param  list<InvoiceLineData>  $lines
     * @return list<array<string, mixed>>
     */
    private function mapLines(array $lines): array
    {
        return array_map(fn (InvoiceLineData $line) => [
            'name' => $line->name,
            'quantity' => $line->quantity,
            'unit_price' => round($line->unitPriceExVat, 2),
            'vat_rate' => $line->vatRate,
        ], $lines);
    }

    /**
     * @param  list<InvoiceLineData>  $lines
     * @return array{tax_exclusive: float, tax: float, tax_inclusive: float}
     */
    private function totals(array $lines): array
    {
        $ex = 0.0;
        $vat = 0.0;

        foreach ($lines as $line) {
            $net = $line->netExVat();
            $ex += $net;
            $vat += $net * ($line->vatRate / 100);
        }

        $ex = round($ex, 2);
        $vat = round($vat, 2);

        return [
            'tax_exclusive' => $ex,
            'tax' => $vat,
            'tax_inclusive' => round($ex + $vat, 2),
        ];
    }
}
