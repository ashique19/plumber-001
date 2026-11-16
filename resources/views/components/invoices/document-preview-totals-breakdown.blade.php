@props([
    'previewTotals',
])

<section class="invoice-issue-preview-totals rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
    <h2 class="mb-2 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
    <p class="text-2xl font-bold tabular-nums">
        {{ number_format($previewTotals['total_inc_vat'], 2) }}
        <span class="text-sm font-medium text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</span>
    </p>
    <dl class="invoice-issue-preview-totals__breakdown mt-3 text-sm">
        <div class="invoice-issue-preview-totals__row">
            <dt class="text-[var(--app-muted)]">{{ __('invoices.subtotal_ex_vat') }}</dt>
            <dd class="font-semibold tabular-nums">{{ number_format((float) ($previewTotals['subtotal_ex_vat'] ?? 0), 2) }}</dd>
        </div>
        <div class="invoice-issue-preview-totals__row">
            <dt class="text-[var(--app-muted)]">{{ __('invoices.total_vat') }}</dt>
            <dd class="font-semibold tabular-nums">{{ number_format((float) ($previewTotals['total_vat'] ?? 0), 2) }}</dd>
        </div>
        @if (($previewTotals['retention_amount'] ?? 0) > 0)
            <div class="invoice-issue-preview-totals__row">
                <dt class="text-[var(--app-muted)]">{{ __('invoices.retention_amount') }}</dt>
                <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['retention_amount'], 2) }}</dd>
            </div>
        @endif
    </dl>
</section>