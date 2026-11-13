<?php

namespace App\Services\Business;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\BusinessSetting;
use App\Services\Pos\PosAnalyticsService;
use Illuminate\Support\Carbon;

class BusinessDashboardService
{
    public function __construct(
        private PosAnalyticsService $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(BusinessSetting $tenant): array
    {
        $tz = config('app.timezone');
        $todayEnd = Carbon::now($tz)->endOfDay();
        $todayStart = Carbon::now($tz)->startOfDay();
        $weekStart = Carbon::now($tz)->subDays(6)->startOfDay();

        $today = $this->analytics->report($tenant, null, $todayStart, $todayEnd, 'day');
        $recent = $this->analytics->report($tenant, null, $weekStart, $todayEnd, 'day');

        return [
            'today' => $today['summary'],
            'recent_sales_series' => $recent['sales_series'],
            'invoices_by_type' => $this->invoiceCountsByType($tenant),
            'signing_queue' => $this->signingQueueCounts($tenant),
        ];
    }

    /**
     * @return list<array{type: string, label_key: string, count: int}>
     */
    private function invoiceCountsByType(BusinessSetting $tenant): array
    {
        $rows = Invoice::query()
            ->withoutGlobalScopes()
            ->where('status', InvoiceStatus::Issued)
            ->selectRaw('document_type, COUNT(*) as aggregate')
            ->groupBy('document_type')
            ->pluck('aggregate', 'document_type');

        $types = [
            InvoiceDocumentType::Simplified,
            InvoiceDocumentType::Standard,
            InvoiceDocumentType::CreditNote,
            InvoiceDocumentType::DebitNote,
        ];

        $out = [];
        foreach ($types as $type) {
            $key = $type->value;
            $out[] = [
                'type' => $key,
                'label_key' => 'dashboard.invoice_type.'.$key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{pending: int, reported: int, failed: int, total: int}
     */
    private function signingQueueCounts(BusinessSetting $tenant): array
    {
        return [
            'pending' => 0,
            'reported' => 0,
            'failed' => 0,
            'total' => 0,
        ];
    }
}
