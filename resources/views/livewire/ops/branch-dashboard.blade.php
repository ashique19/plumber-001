@php
    $points = collect($today['hourly']);
    $hourlyCount = $points->count();
    $hourSlotSpan = max(1, $hourlyCount - 1);
@endphp
<div>
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('pos.ops_dashboard') }}</h1>
        <p class="app-page-lead">{{ $branch->name }} · {{ __('pos.shift_today') }}</p>
    </header>

    <div class="app-kpi-grid">
        <x-app.stat-card :label="__('pos.orders_paid')" :value="(string) $today['order_count']" icon="clipboard" />
        <x-app.stat-card :label="__('pos.gross_inc_vat')" :value="number_format($today['gross_inc_vat'], 2).' SAR'" icon="chart" />
        <x-app.stat-card :label="__('pos.vat')" :value="number_format($today['vat_total'], 2).' SAR'" icon="clock" />
    </div>

    <section class="app-card mt-4 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('pos.sales_by_hour') }}</h2>
        @if($points->isEmpty())
            <p class="mt-3 text-sm text-[var(--app-muted)]">{{ __('pos.no_sales_yet') }}</p>
        @else
            <svg class="pos-sparkline mt-3" viewBox="0 0 240 64" preserveAspectRatio="none" role="img" aria-label="{{ __('pos.sales_by_hour') }}">
                @foreach($today['hourly'] as $i => $row)
                    @php
                        $barH = ($row['total'] / $maxHour) * 56;
                        $x = $hourlyCount === 1 ? 110 : ($i * (220 / $hourSlotSpan) + 10);
                    @endphp
                    <rect x="{{ $x }}" y="{{ 60 - $barH }}" width="14" height="{{ max(2, $barH) }}" rx="3" fill="var(--cafe-caramel)" opacity="0.85" />
                @endforeach
            </svg>
        @endif
    </section>

    <section class="app-card mt-4 p-4">
        <h2 class="text-sm font-semibold">{{ __('pos.top_products') }}</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse($today['top_products'] as $row)
                <li class="flex justify-between gap-2">
                    <span>{{ $row['name'] }} <span class="text-[var(--app-muted)]">×{{ $row['qty'] }}</span></span>
                    <span class="tabular-nums font-medium">{{ number_format($row['total'], 2) }}</span>
                </li>
            @empty
                <li class="text-[var(--app-muted)]">{{ __('pos.no_sales_yet') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="app-card mt-4 p-4">
        <h2 class="text-sm font-semibold">{{ __('pos.recent_orders') }}</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach($today['recent'] as $recent)
                <li class="flex items-center justify-between gap-2 border-b border-[var(--app-border)] pb-2">
                    <span class="font-medium">#{{ $recent->order_number }}</span>
                    <span class="pos-status-pill pos-status-pill--{{ $recent->status->value }}">{{ $recent->status->value }}</span>
                    <span class="tabular-nums">{{ number_format($recent->total_inc_vat, 2) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
