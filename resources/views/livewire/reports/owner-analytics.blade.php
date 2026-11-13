@php
    $summary = $report['summary'];
    $trend = $summary['trend_gross_pct'];
    $trendUp = $trend >= 0;
@endphp
<div class="owner-analytics pb-24 lg:pb-8">
    @vite(['resources/css/analytics-dashboard.css', 'resources/js/analytics-dashboard.js'])

    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('reports.title') }}</h1>
            <p class="app-page-lead">{{ __('reports.subtitle') }}</p>
        </div>
        <a
            href="{{ route('admin.reports.export', [
                'tenant' => $tenant,
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
    </header>

    <section class="app-card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
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
            <label class="form-field min-w-[9rem] flex-1">
                <span class="form-field__label">{{ __('reports.branch_filter') }}</span>
                <select wire:model.live="branchId" class="form-field__control">
                    <option value="">{{ __('reports.all_branches') }}</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <div class="app-kpi-grid mt-4">
        <x-app.stat-card :label="__('reports.orders')" :value="(string) $summary['order_count']" icon="clipboard" />
        <x-app.stat-card :label="__('reports.gross')" :value="number_format($summary['gross_inc_vat'], 2).' SAR'" icon="chart" />
        <x-app.stat-card :label="__('reports.vat_collected')" :value="number_format($summary['vat_total'], 2).' SAR'" icon="shield" />
        <x-app.stat-card :label="__('reports.aov')" :value="number_format($summary['avg_order_value'], 2).' SAR'" icon="clock" />
    </div>

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
</div>
