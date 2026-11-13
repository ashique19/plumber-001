@php
    $maxPayment = max(1, (float) collect($summary['by_payment'] ?? [])->max('total_inc_vat'));
@endphp
<div>
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('pos.end_of_day') }}</h1>
        <p class="app-page-lead">{{ $branch->name }}</p>
    </header>
    <label class="app-card app-card--flat mb-4 block max-w-xs p-3 text-sm font-medium">
        {{ __('pos.date') }}
        <input type="date" wire:model.live="date" class="field-touch mt-2">
    </label>
    <div class="app-kpi-grid">
        <x-app.stat-card :label="__('pos.orders_paid')" :value="(string) $summary['order_count']" icon="clipboard" />
        <x-app.stat-card :label="__('pos.gross_inc_vat')" :value="number_format($summary['gross_inc_vat'], 2).' SAR'" icon="chart" />
        <x-app.stat-card :label="__('pos.vat')" :value="number_format($summary['vat_total'], 2).' SAR'" icon="clock" />
    </div>
    <section class="app-card mt-4 p-4 sm:p-5">
        <h2 class="text-base font-semibold">{{ __('pos.cash_count') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="text-sm">{{ __('pos.cash_drawer_counted') }}
                <input type="number" step="0.01" min="0" class="field-touch mt-1" placeholder="{{ number_format($summary['by_payment']['cash']['total_inc_vat'] ?? 0, 2) }}">
            </label>
            <label class="text-sm">{{ __('pos.card_terminal_total') }}
                <input type="number" step="0.01" min="0" class="field-touch mt-1" placeholder="{{ number_format($summary['by_payment']['card']['total_inc_vat'] ?? 0, 2) }}">
            </label>
        </div>
        <p class="mt-2 text-xs text-[var(--app-muted)]">{{ __('pos.cash_count_hint') }}</p>
    </section>
    <section class="app-card mt-4 p-4 sm:p-5">
        <h2 class="text-base font-semibold">{{ __('pos.by_payment') }}</h2>
        <ul class="mt-4 space-y-4">
            @foreach($summary['by_payment'] as $method => $row)
                @php $pct = min(100, round(((float) $row['total_inc_vat'] / $maxPayment) * 100)); @endphp
                <li>
                    <div class="flex justify-between gap-2 text-sm">
                        <span class="font-medium capitalize">{{ $method }}</span>
                        <span class="tabular-nums text-[var(--app-muted)]">{{ $row['count'] }} · {{ number_format($row['total_inc_vat'], 2) }} SAR</span>
                    </div>
                    <div class="app-chart-bar mt-2" role="presentation"><span style="width: {{ $pct }}%"></span></div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
