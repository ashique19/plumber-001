@props([
    'heading',
    'summaryTotals',
    'grandLabel' => null,
])

@php
    $grandLabel = $grandLabel ?? __('invoices.grand_total');
@endphp

<div class="document-create-card document-create-summary">
    <div class="document-create-card__head">
        <x-app.icon name="calculator" class="h-5 w-5" aria-hidden="true" />
        <span>{{ $heading }}</span>
    </div>
    <div class="document-create-card__body" aria-live="polite">
        <dl class="document-summary-rows">
            <div>
                <dt class="text-[var(--app-muted)]">{{ __('invoices.summary_subtotal') }}</dt>
                <dd class="tabular-nums font-semibold">{{ number_format($summaryTotals['subtotal_ex_vat'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-[var(--app-muted)]">{{ __('invoices.summary_discount') }}</dt>
                <dd class="tabular-nums font-semibold">{{ number_format($summaryTotals['discount_ex_vat'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-[var(--app-muted)]">{{ __('invoices.summary_taxable') }}</dt>
                <dd class="tabular-nums font-semibold">{{ number_format($summaryTotals['taxable_ex_vat'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-[var(--app-muted)]">{{ __('invoices.summary_vat', ['rate' => 15]) }}</dt>
                <dd class="tabular-nums font-semibold">{{ number_format($summaryTotals['total_vat'], 2) }}</dd>
            </div>
        </dl>
        <div class="document-summary-grand">
            <span>{{ $grandLabel }}</span>
            <span class="tabular-nums">{{ number_format($summaryTotals['grand_total_inc_vat'], 2) }} {{ __('invoices.riyal_symbol') }}</span>
        </div>
    </div>
</div>