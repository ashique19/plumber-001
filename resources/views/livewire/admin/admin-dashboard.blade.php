@php
    $today = $snapshot['today'];
@endphp
<div class="admin-dashboard pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('dashboard.title') }}</h1>
            <p class="app-page-lead">{{ __('dashboard.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reports') }}" class="tap-action-muted min-h-11 items-center gap-2 px-4 text-sm">
                <x-app.icon name="chart" class="h-4 w-4" />
                {{ __('dashboard.open_analytics') }}
            </a>
            @can('invoices.issue')
                <a href="{{ route('admin.invoices') }}" class="tap-action-muted min-h-11 items-center gap-2 px-4 text-sm">
                    <x-app.icon name="clipboard" class="h-4 w-4" />
                    {{ __('dashboard.signing_queue_link') }}
                </a>
            @endcan
        </div>
    </header>

    @if(count($lowStock ?? []) > 0)
        <section class="app-card mb-4 border-amber-200 bg-amber-50/90 p-4">
            <h2 class="text-sm font-semibold text-amber-950">{{ __('inventory.dashboard_low_stock') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach($lowStock as $ing)
                    <li class="flex justify-between gap-2">
                        <span>{{ $ing->displayName() }}</span>
                        <span class="tabular-nums font-medium">{{ number_format((float) $ing->quantity_on_hand, 2) }} {{ $ing->unit }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="app-kpi-grid">
        <x-app.stat-card :label="__('dashboard.today_orders')" :value="(string) $today['order_count']" icon="clipboard" />
        <x-app.stat-card :label="__('dashboard.today_sales')" :value="number_format($today['gross_inc_vat'], 2).' '.__('dashboard.sar')" icon="chart" />
        <x-app.stat-card :label="__('dashboard.today_vat')" :value="number_format($today['vat_total'], 2).' '.__('dashboard.sar')" icon="shield" />
        <x-app.stat-card
            :label="__('dashboard.today_aov')"
            :value="number_format($today['avg_order_value'], 2).' '.__('dashboard.sar')"
            icon="clock"
        />
    </div>

    <section class="app-card mt-4 p-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ __('dashboard.recent_sales') }}</h2>
            <span class="text-xs text-[var(--app-muted)]">{{ __('dashboard.last_7_days') }}</span>
        </div>
        @if ($series === [])
            <p class="mt-4 text-sm text-[var(--app-muted)]">{{ __('dashboard.no_sales_yet') }}</p>
        @else
            <div class="mt-4 flex items-end justify-between gap-1 sm:gap-2" role="img" aria-label="{{ __('dashboard.recent_sales') }}">
                @foreach ($series as $row)
                    @php
                        $barHeight = (int) round(((float) $row['total'] / $maxTotal) * 96);
                        $barHeight = max(4, $barHeight);
                    @endphp
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                        <span class="text-[0.65rem] font-medium tabular-nums text-[var(--app-muted)]" title="{{ number_format($row['total'], 2) }}">
                            {{ $row['orders'] > 0 ? number_format($row['total'], 0) : '—' }}
                        </span>
                        <div
                            class="w-full max-w-12 rounded-t-md bg-[var(--cafe-caramel)] transition-[height]"
                            style="height: {{ $barHeight }}px; min-height: 4px; opacity: {{ $row['total'] > 0 ? 1 : 0.25 }}"
                        ></div>
                        <span class="truncate text-[0.6rem] font-semibold text-[var(--app-muted)]">{{ $row['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="app-card p-4">
            <h2 class="text-sm font-semibold">{{ __('dashboard.invoices_by_type') }}</h2>
            <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('dashboard.invoices_issued_hint') }}</p>
            <ul class="mt-4 space-y-3">
                @foreach ($snapshot['invoices_by_type'] as $row)
                    @php
                        $barPct = $invoiceMax > 0 ? round(($row['count'] / $invoiceMax) * 100) : 0;
                    @endphp
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ __($row['label_key']) }}</span>
                            <span class="font-semibold tabular-nums">{{ $row['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-[var(--app-border)]">
                            <div
                                class="h-full rounded-full bg-[var(--cafe-accent)]"
                                style="width: {{ $barPct }}%"
                            ></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="app-card p-4">
            <h2 class="text-sm font-semibold">{{ __('dashboard.signing_queue') }}</h2>
            <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('dashboard.signing_queue_hint') }}</p>
            @if ($signingQueue['total'] === 0)
                <p class="mt-4 text-sm text-[var(--app-muted)]">{{ __('dashboard.no_submissions') }}</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ([
                        'pending' => __('dashboard.signing_pending'),
                        'reported' => __('dashboard.signing_reported'),
                        'failed' => __('dashboard.signing_failed'),
                    ] as $key => $label)
                        @php
                            $count = $signingQueue[$key];
                            $barPct = round(($count / $signingMax) * 100);
                        @endphp
                        <li>
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span>{{ $label }}</span>
                                <span class="font-semibold tabular-nums">{{ $count }}</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-[var(--app-border)]">
                                <div
                                    @class([
                                        'h-full rounded-full',
                                        'bg-amber-500/80' => $key === 'pending',
                                        'bg-emerald-600/80' => $key === 'reported',
                                        'bg-red-600/70' => $key === 'failed',
                                    ])
                                    style="width: {{ $barPct }}%"
                                ></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
