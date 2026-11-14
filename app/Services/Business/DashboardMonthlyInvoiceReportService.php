<?php

namespace App\Services\Business;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\InvoicePayloadTotals;
use App\Support\Reconcile\ReconcileIssueDateFilter;
use Illuminate\Support\Carbon;

class DashboardMonthlyInvoiceReportService
{
    public const BUSINESS_TZ = ReconcileIssueDateFilter::BUSINESS_TZ;

  /** @var list<InvoiceDocumentType> */
    private const TYPES = [
        InvoiceDocumentType::Simplified,
        InvoiceDocumentType::Standard,
        InvoiceDocumentType::CreditNote,
        InvoiceDocumentType::DebitNote,
    ];

    /**
     * Last three calendar months in business timezone (current month first).
     *
     * @return list<array<string, mixed>>
     */
    public function report(BusinessSetting $business, ?Branch $branch, ?string $locale = null): array
    {
        unset($business);

        $locale = $locale ?? app()->getLocale();
        $tz = self::BUSINESS_TZ;
        $months = [];
        $cursor = Carbon::now($tz)->startOfMonth();

        for ($i = 0; $i < 3; $i++) {
            $monthStart = $cursor->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
            $startUtc = $monthStart->copy()->timezone('UTC');
            $endUtc = $monthEnd->copy()->timezone('UTC');

            $months[] = $this->aggregateMonth(
                $monthStart,
                $startUtc,
                $endUtc,
                $locale,
                $branch,
            );
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateMonth(
        Carbon $monthStartLocal,
        Carbon $startUtc,
        Carbon $endUtc,
        string $locale,
        ?Branch $branch,
    ): array {
        $emptyType = static fn (): array => ['count' => 0, 'total_inc_vat' => 0.0, 'vat' => 0.0];

        $byType = [];
        foreach (self::TYPES as $type) {
            $byType[$type->value] = $emptyType();
        }

        $signedVatByType = [];
        foreach (self::TYPES as $type) {
            $signedVatByType[$type->value] = 0.0;
        }

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$startUtc, $endUtc])
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->get();

        foreach ($invoices as $invoice) {
            $typeKey = $invoice->document_type->value;
            if (! isset($byType[$typeKey])) {
                continue;
            }

            $totals = InvoicePayloadTotals::forInvoice($invoice);
            $vat = round((float) $totals['total_vat'], 2);
            $inc = round((float) $totals['total_inc_vat'], 2);

            $byType[$typeKey]['count']++;
            $byType[$typeKey]['vat'] = round($byType[$typeKey]['vat'] + $vat, 2);
            $byType[$typeKey]['total_inc_vat'] = round($byType[$typeKey]['total_inc_vat'] + $inc, 2);

            $sign = $this->netSign($invoice->document_type);
            $signedVatByType[$typeKey] = round($signedVatByType[$typeKey] + ($sign * $vat), 2);
        }

        $netVat = round(array_sum($signedVatByType), 2);

        return [
            'month_key' => $monthStartLocal->format('Y-m'),
            'month_label' => $this->formatMonthLabel($monthStartLocal, $locale),
            'net_vat' => $netVat,
            'signed_vat_by_type' => $signedVatByType,
            'by_type' => $byType,
        ];
    }

    private function netSign(InvoiceDocumentType $type): int
    {
        return match ($type) {
            InvoiceDocumentType::CreditNote => -1,
            default => 1,
        };
    }

    private function formatMonthLabel(Carbon $monthStart, string $locale): string
    {
        $carbonLocale = match ($locale) {
            'bn' => 'bn',
            'ar' => 'ar',
            default => 'en',
        };

        return $monthStart->copy()->locale($carbonLocale)->translatedFormat('F Y');
    }
}