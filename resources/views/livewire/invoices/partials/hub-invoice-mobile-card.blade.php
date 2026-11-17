@php
    /** @var array<string, mixed> $row */
    $invoice = $row['invoice'];
    $urls = $row['print_urls'];
    $paymentKey = $row['payment_status']?->value;
    $a = $row['actions'];
    $rowUrl = $row['row_url'] ?? null;
@endphp
<li wire:key="inv-card-{{ $invoice->id }}" class="hub-doc-mobile-card" data-testid="hub-invoice-mobile-card">
    @if (filled($rowUrl))
        <a href="{{ $rowUrl }}" wire:navigate class="hub-doc-mobile-card__hit" data-testid="hub-invoice-mobile-row-link">
    @endif
    <div class="hub-doc-mobile-card__head">
        <div>
            <p class="font-semibold tabular-nums">{{ $row['display_number'] }}</p>
            <p class="text-xs text-[var(--app-muted)]">{{ $row['customer'] }}</p>
        </div>
        @if ($row['payment_status'] === null)
            <span class="text-sm text-[var(--app-muted)]">—</span>
        @else
            <span @class([
                'pos-status-pill',
                'pos-status-pill--paid' => $paymentKey === 'paid',
                'pos-status-pill--warning' => $paymentKey === 'partial',
                'pos-status-pill--refunded' => $paymentKey === 'unpaid',
            ])>{{ $row['payment_status']->label() }}</span>
        @endif
    </div>
    <dl class="hub-doc-mobile-card__meta">
        <div class="hub-doc-mobile-card__meta-row">
            <dt class="text-[var(--app-muted)]">{{ __('invoices.col_date') }}</dt>
            <dd class="tabular-nums">{{ $invoice->issued_at?->format('Y-m-d') ?? ($invoice->created_at?->format('Y-m-d') ?? '—') }}</dd>
        </div>
        <div class="hub-doc-mobile-card__meta-row">
            <dt class="text-[var(--app-muted)]">{{ __('invoices.col_total') }}</dt>
            <dd class="tabular-nums font-medium"><x-invoices.sar-symbol /> {{ number_format($row['total_inc_vat'], 2) }}</dd>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="invoice" />
            <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="zatca" :zatca-error="$row['zatca_error']" />
        </div>
    </dl>
    @if (filled($rowUrl))
        </a>
    @endif
    <div class="hub-doc-mobile-card__actions" @if (filled($rowUrl)) onclick="event.stopPropagation()" @endif>
        <x-hub.invoice-row-actions
            :invoice-id="$invoice->id"
            :actions="$a"
            :more="$row['more']"
            :view-url="$row['view_url']"
            :download-url="$urls['download']"
            :print-url="$urls['print']"
            :print-a4-url="$urls['a4'] ?? null"
            :print-cash-url="$urls['cash'] ?? null"
            :print-layout="$urls['layout'] ?? 'a4'"
            :print-back-url="$urls['fallback_back'] ?? route('admin.invoices')"
            :can-resubmit-zatca="$canResubmitZatca ?? false"
        />
    </div>
</li>