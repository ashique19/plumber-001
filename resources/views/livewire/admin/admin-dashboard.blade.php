@php
    $today = $snapshot['today'];
    $docTypes = ['simplified', 'standard', 'credit_note', 'debit_note'];
    $isRtl = config('locales.supported.'.app()->getLocale().'.dir', 'ltr') === 'rtl';
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

    @if ($canIssueInvoice || $canAddProducts)
        <section class="dashboard-quick-actions mb-4 flex flex-col gap-3" aria-label="{{ __('dashboard.quick_actions') }}">
            @if ($canIssueInvoice)
                <a
                    href="{{ route('admin.invoices.issue', $invoiceIssueMode) }}"
                    class="tap-action-primary flex min-h-14 w-full items-center justify-center gap-2 px-5 text-base font-semibold sm:text-lg"
                >
                    <x-app.icon name="clipboard" class="h-5 w-5 shrink-0" />
                    {{ __('dashboard.make_invoice') }}
                </a>
            @endif
            @if ($canAddProducts)
                <a
                    href="{{ route('admin.catalog', ['create' => 'product']) }}"
                    class="tap-action-primary flex min-h-14 w-full items-center justify-center gap-2 px-5 text-base font-semibold sm:text-lg"
                >
                    <x-app.icon name="plus" class="h-5 w-5 shrink-0" />
                    {{ __('dashboard.add_products') }}
                </a>
            @endif
        </section>
    @endif

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
    </div>

    <section
        class="dashboard-monthly-report mt-4 space-y-4"
        aria-label="{{ __('dashboard.monthly_report.title') }}"
        {!! $isRtl ? 'dir="rtl"' : '' !!}
    >
        <header>
            <h2 class="text-base font-semibold">{{ __('dashboard.monthly_report.title') }}</h2>
            <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('dashboard.monthly_report.hint') }}</p>
        </header>

        @foreach ($monthlyReport as $month)
            <article class="app-card overflow-hidden p-0">
                <div class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 px-4 py-3">
                    <h3 class="text-sm font-semibold">{{ $month['month_label'] }}</h3>
                </div>
                <div class="space-y-4 p-4">
                    <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] p-3">
                        <p class="text-xs font-medium text-[var(--app-muted)]">{{ __('dashboard.monthly_report.net_vat') }}</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ number_format($month['net_vat'], 2) }} {{ __('dashboard.sar') }}
                        </p>
                        <ul class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                            @foreach ($docTypes as $typeKey)
                                <li class="flex items-center justify-between gap-2 border-t border-[var(--app-border)] pt-2 first:border-t-0 first:pt-0 sm:first:border-t sm:first:pt-2">
                                    <span>{{ __('dashboard.monthly_report.doc_types.'.$typeKey) }}</span>
                                    <span class="tabular-nums font-medium">
                                        {{ number_format($month['signed_vat_by_type'][$typeKey] ?? 0, 2) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                        <table class="dashboard-monthly-report__table w-full min-w-[32rem] text-left text-xs sm:text-sm">
                            <thead>
                                <tr class="border-b border-[var(--app-border)] text-[var(--app-muted)]">
                                    <th class="pb-2 pe-2 font-medium">{{ __('dashboard.monthly_report.document') }}</th>
                                    <th class="pb-2 px-2 text-end font-medium">{{ __('dashboard.monthly_report.count') }}</th>
                                    <th class="pb-2 px-2 text-end font-medium">{{ __('dashboard.monthly_report.total_inc_vat') }}</th>
                                    <th class="pb-2 ps-2 text-end font-medium">{{ __('dashboard.monthly_report.vat') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($docTypes as $typeKey)
                                    @php
                                        $row = $month['by_type'][$typeKey] ?? ['count' => 0, 'total_inc_vat' => 0, 'vat' => 0];
                                    @endphp
                                    <tr class="border-b border-[var(--app-border)] last:border-0">
                                        <td class="py-2.5 pe-2 font-medium">{{ __('dashboard.monthly_report.doc_types.'.$typeKey) }}</td>
                                        <td class="py-2.5 px-2 text-end tabular-nums">{{ $row['count'] }}</td>
                                        <td class="py-2.5 px-2 text-end tabular-nums">
                                            {{ number_format($row['total_inc_vat'], 2) }} {{ __('dashboard.sar') }}
                                        </td>
                                        <td class="py-2.5 ps-2 text-end tabular-nums">
                                            {{ number_format($row['vat'], 2) }} {{ __('dashboard.sar') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

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
                            class="w-full max-w-12 rounded-t-md bg-[var(--plumber-safety)] transition-[height]"
                            style="height: {{ $barHeight }}px; min-height: 4px; opacity: {{ $row['total'] > 0 ? 1 : 0.25 }}"
                        ></div>
                        <span class="truncate text-[0.6rem] font-semibold text-[var(--app-muted)]">{{ $row['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
