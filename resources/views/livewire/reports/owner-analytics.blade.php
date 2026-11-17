@php
    use App\Support\Reports\OwnerAnalyticsSection;

    $summary = $report['summary'];
    $trend = $summary['trend_gross_pct'];
    $trendUp = $trend >= 0;
    $extended = $report['extended'] ?? [];
    $vatSummary = $extended['vat_summary'] ?? [];
    $profit = $extended['profit'] ?? [];
    $inventory = $extended['inventory'] ?? [];
    $dues = $extended['dues_aging'] ?? [];
    $showsSummaryKpis = OwnerAnalyticsSection::showsSummaryKpis($reportSection);
    $showsCharts = OwnerAnalyticsSection::showsCharts($reportSection);
    $showsVatSummary = OwnerAnalyticsSection::showsVatSummary($reportSection);
    $showsProfit = OwnerAnalyticsSection::showsProfit($reportSection);
    $showsInventory = OwnerAnalyticsSection::showsInventory($reportSection);
    $showsDues = OwnerAnalyticsSection::showsDues($reportSection);
@endphp
<div class="owner-analytics min-w-0 max-w-full overflow-x-hidden pb-24 lg:pb-8">
    @vite(['resources/css/reports-print.css'])

    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __(OwnerAnalyticsSection::titleKey($reportSection)) }}</h1>
            <p class="app-page-lead">{{ __(OwnerAnalyticsSection::subtitleKey($reportSection)) }}</p>
        </div>
        <div class="flex flex-wrap gap-2 reports-no-print">
            <button
                type="button"
                onclick="window.print()"
                class="tap-action-muted inline-flex min-h-11 items-center justify-center gap-2 px-4 text-sm"
            >
                <x-app.icon name="clipboard" class="h-4 w-4" />
                {{ __('reports.print') }}
            </button>
            <a
                href="{{ route('admin.reports.export', [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'granularity' => $granularity,
                    'branch' => $branchId,
                ]) }}"
                class="tap-action-muted inline-flex min-h-11 items-center justify-center gap-2 px-4 text-sm"
            >
                <x-app.icon name="external" class="h-4 w-4" />
                {{ __('reports.export_csv') }}
            </a>
        </div>
    </header>

    <section class="app-card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end reports-no-print">
        <div class="app-section-tabs">
            @foreach (['7d' => __('reports.preset_7d'), '30d' => __('reports.preset_30d'), '90d' => __('reports.preset_90d'), 'month' => __('reports.preset_month')] as $key => $label)
                <button
                    type="button"
                    wire:click="applyPreset('{{ $key }}')"
                    @class([
                        'app-segment-tab',
                        'app-segment-tab--active' => $preset === $key,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex flex-1 flex-wrap gap-2">
            <label class="form-field min-w-[9rem] flex-1">
                <span class="form-field__label">{{ __('reports.from') }}</span>
                <input type="date" wire:model.live="dateFrom" class="form-field__control">
            </label>
            <label class="form-field min-w-[9rem] flex-1">
                <span class="form-field__label">{{ __('reports.to') }}</span>
                <input type="date" wire:model.live="dateTo" class="form-field__control">
            </label>
            <label class="form-field min-w-[9rem] flex-1">
                <span class="form-field__label">{{ __('reports.granularity') }}</span>
                <select wire:model.live="granularity" class="form-field__control">
                    <option value="day">{{ __('reports.grain_day') }}</option>
                    <option value="week">{{ __('reports.grain_week') }}</option>
                    <option value="month">{{ __('reports.grain_month') }}</option>
                </select>
            </label>
            @if ($showBranchSelector)
                <label class="form-field min-w-[9rem] flex-1">
                    <span class="form-field__label">{{ __('reports.branch_filter') }}</span>
                    <select wire:model.live="branchId" class="form-field__control">
                        <option value="">{{ __('reports.all_branches') }}</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif ($resolvedBranch)
                <p class="flex min-h-[2.75rem] flex-col justify-center text-sm text-[var(--app-muted)]">
                    <span class="form-field__label">{{ __('reports.branch_filter') }}</span>
                    <span class="font-medium text-[var(--app-fg)]">{{ $resolvedBranch->name }}</span>
                </p>
            @endif
        </div>
    </section>

    @if ($showsSummaryKpis)
        <div class="app-kpi-grid mt-4">
            <x-app.stat-card :label="__('reports.orders')" :value="(string) $summary['order_count']" icon="clipboard" />
            <x-app.stat-card :label="__('reports.gross')" :value="number_format($summary['gross_inc_vat'], 2).' SAR'" icon="chart" />
            <x-app.stat-card :label="__('reports.vat_collected')" :value="number_format($summary['vat_total'], 2).' SAR'" icon="shield" />
            <x-app.stat-card :label="__('reports.aov')" :value="number_format($summary['avg_order_value'], 2).' SAR'" icon="clock" />
        </div>
    @endif

    @if ($extended !== [] && ($showsVatSummary || $showsProfit || $showsInventory || $showsDues))
        <section class="reports-print-block mt-4 grid gap-4 lg:grid-cols-2">
            @if ($showsVatSummary)
            <div class="app-card p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.vat_summary_heading') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-[var(--app-muted)]">{{ __('reports.taxable_ex_vat') }}</dt>
                        <dd class="tabular-nums font-medium">{{ number_format($vatSummary['taxable_ex_vat'] ?? 0, 2) }} SAR</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-[var(--app-muted)]">{{ __('reports.vat_collected') }}</dt>
                        <dd class="tabular-nums font-medium">{{ number_format($vatSummary['vat_collected'] ?? 0, 2) }} SAR</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-[var(--app-muted)]">{{ __('reports.gross_inc_vat') }}</dt>
                        <dd class="tabular-nums font-medium">{{ number_format($vatSummary['gross_inc_vat'] ?? 0, 2) }} SAR</dd>
                    </div>
                </dl>
            </div>
            @endif

            @if ($showsProfit)
            <div class="app-card p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.profit_heading') }}</h2>
                @if (($profit['cost_status'] ?? 'unknown') === 'unknown')
                    <p class="mt-3 text-sm text-[var(--app-muted)]">{{ __('reports.cost_unknown') }}</p>
                    <p class="mt-2 tabular-nums text-sm font-medium">
                        {{ __('reports.sales_ex_vat') }}: {{ number_format($profit['sales_ex_vat'] ?? 0, 2) }} SAR
                    </p>
                    @if (($profit['operating_expenses_ex_vat'] ?? 0) > 0)
                        <p class="mt-2 tabular-nums text-sm">
                            {{ __('reports.operating_expenses_ex_vat') }}:
                            {{ number_format($profit['operating_expenses_ex_vat'], 2) }} SAR
                        </p>
                    @endif
                @else
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-[var(--app-muted)]">{{ __('reports.sales_ex_vat') }}</dt>
                            <dd class="tabular-nums font-medium">{{ number_format($profit['sales_ex_vat'] ?? 0, 2) }} SAR</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[var(--app-muted)]">{{ __('reports.cogs_ex_vat') }}</dt>
                            <dd class="tabular-nums font-medium">{{ number_format($profit['cogs_ex_vat'] ?? 0, 2) }} SAR</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[var(--app-muted)]">{{ __('reports.gross_profit_ex_vat') }}</dt>
                            <dd class="tabular-nums font-medium">{{ number_format($profit['gross_profit_ex_vat'] ?? 0, 2) }} SAR</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-[var(--app-muted)]">{{ __('reports.operating_expenses_ex_vat') }}</dt>
                            <dd class="tabular-nums font-medium">{{ number_format($profit['operating_expenses_ex_vat'] ?? 0, 2) }} SAR</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-[var(--app-border)] pt-2">
                            <dt class="font-medium text-[var(--app-text)]">{{ __('reports.net_profit_ex_vat') }}</dt>
                            <dd class="tabular-nums font-semibold">{{ number_format($profit['net_profit_ex_vat'] ?? 0, 2) }} SAR</dd>
                        </div>
                        @if (($profit['cost_status'] ?? '') === 'partial')
                            <p class="text-xs text-amber-800">{{ __('reports.cost_partial') }}</p>
                        @endif
                    </dl>
                @endif
            </div>
            @endif

            @if ($showsInventory)
            <div class="app-card p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.inventory_heading') }}</h2>
                @if (($inventory['valuation_status'] ?? '') === 'known')
                    <p class="mt-2 text-sm font-medium tabular-nums">
                        {{ __('reports.inventory_valuation') }}: {{ number_format($inventory['valuation_total_ex_vat'] ?? 0, 2) }} SAR
                    </p>
                @else
                    <p class="mt-2 text-sm text-[var(--app-muted)]">{{ __('reports.valuation_unknown') }}</p>
                @endif
                @if (count($inventory['low_stock'] ?? []) > 0)
                    <h3 class="mt-4 text-xs font-semibold uppercase text-amber-900">{{ __('reports.low_stock_heading') }}</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($inventory['low_stock'] as $ing)
                            <li class="flex justify-between gap-4">
                                <span>{{ $ing['name'] }}</span>
                                <span class="tabular-nums text-[var(--app-muted)]">
                                    {{ number_format($ing['quantity'], 2) }} {{ $ing['unit'] }}
                                    / {{ __('reports.threshold') }} {{ number_format($ing['threshold'], 2) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-[var(--app-muted)]">{{ __('reports.no_low_stock') }}</p>
                @endif
            </div>
            @endif

            @if ($showsDues)
            <div class="app-card p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.dues_heading') }}</h2>
                <p class="mt-1 text-xs text-[var(--app-muted)]">
                    {{ __('reports.dues_as_of', ['date' => $dues['as_of'] ?? $dateTo]) }}
                </p>
                <p class="mt-2 text-sm font-medium tabular-nums">
                    {{ __('reports.total_outstanding') }}: {{ number_format($dues['total_outstanding_inc_vat'] ?? 0, 2) }} SAR
                </p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead>
                            <tr class="text-xs uppercase text-[var(--app-muted)]">
                                <th class="pb-2 pe-4">{{ __('reports.aging_bucket') }}</th>
                                <th class="pb-2 pe-4">{{ __('reports.aging_count') }}</th>
                                <th class="pb-2">{{ __('reports.aging_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                'current' => __('reports.bucket_current'),
                                'days_1_30' => __('reports.bucket_1_30'),
                                'days_31_60' => __('reports.bucket_31_60'),
                                'days_61_90' => __('reports.bucket_61_90'),
                                'days_90_plus' => __('reports.bucket_90_plus'),
                            ] as $key => $label)
                                @php
                                    $bucket = $dues['buckets'][$key] ?? ['count' => 0, 'total_inc_vat' => 0];
                                @endphp
                                <tr class="border-t border-[var(--app-border)]">
                                    <td class="py-2 pe-4">{{ $label }}</td>
                                    <td class="py-2 pe-4 tabular-nums">{{ $bucket['count'] }}</td>
                                    <td class="py-2 tabular-nums">{{ number_format($bucket['total_inc_vat'], 2) }} SAR</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </section>
    @endif

    @if ($showsCharts)
    @vite(['resources/css/analytics-dashboard.css', 'resources/js/analytics-dashboard.js'])
    @php
        $analyticsPayload = [
            'report' => $report,
            'theme' => $chartTheme,
            'rtl' => $isRtl,
            'labels' => [
                'sales' => __('reports.chart_sales'),
                'sar' => __('reports.sar'),
                'categories' => __('reports.chart_categories'),
                'payments' => __('reports.chart_payments'),
                'branches' => __('reports.chart_branches'),
                'cashiers' => __('reports.chart_cashiers'),
                'topProducts' => __('reports.top_products'),
                'heatmap_zero' => __('reports.heatmap_zero'),
                'heatmap_low' => __('reports.heatmap_low'),
                'heatmap_mid' => __('reports.heatmap_mid'),
                'heatmap_high' => __('reports.heatmap_high'),
                'dow' => [
                    __('reports.dow_mon'),
                    __('reports.dow_tue'),
                    __('reports.dow_wed'),
                    __('reports.dow_thu'),
                    __('reports.dow_fri'),
                    __('reports.dow_sat'),
                    __('reports.dow_sun'),
                ],
            ],
            'paymentLabels' => [
                'cash' => __('pos.cash'),
                'card' => __('pos.card'),
                'mada' => __('pos.mada'),
                'other' => __('pos.other'),
            ],
        ];
    @endphp
    <script type="application/json" id="analytics-payload">{!! json_encode($analyticsPayload, JSON_UNESCAPED_UNICODE) !!}</script>

    <div class="mt-4 grid gap-4 lg:grid-cols-2" wire:ignore id="analytics-charts-root">
        <section class="app-card p-3 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.sales_over_time') }}</h2>
            <div id="chart-sales-series" class="analytics-chart mt-2"></div>
        </section>
        <section class="app-card p-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.sales_by_hour') }}</h2>
            <div id="chart-heatmap" class="analytics-chart analytics-chart--tall mt-2"></div>
        </section>
        <section class="app-card p-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.category_mix') }}</h2>
            <div id="chart-category-donut" class="analytics-chart mt-2"></div>
        </section>
        <section class="app-card p-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.payment_split') }}</h2>
            <div id="chart-payment-donut" class="analytics-chart mt-2"></div>
        </section>
        <section class="app-card p-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.top_products') }}</h2>
            <div id="chart-top-products" class="analytics-chart mt-2"></div>
        </section>
        @if (count($report['branch_comparison']) > 0)
            <section class="app-card p-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.branch_comparison') }}</h2>
                <div id="chart-branches" class="analytics-chart mt-2"></div>
            </section>
        @endif
        <section class="app-card p-3 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('reports.cashier_performance') }}</h2>
            <div id="chart-cashiers" class="analytics-chart mt-2"></div>
        </section>
    </div>
    @endif
</div>
