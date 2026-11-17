@php
    /** @var \App\Models\Invoice $invoice */
@endphp

@if (! ($isPdf ?? false) && $invoice->isIssued())
    <section
        class="invoice-view-zatca no-print mx-2 mb-2 rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-3"
        aria-label="{{ __('invoices.zatca_view_panel_label') }}"
        data-testid="invoice-view-zatca-panel"
    >
        <h2 class="m-0 mb-2 text-sm font-semibold text-[var(--app-text)]">{{ __('invoices.zatca_view_tab') }}</h2>

        @foreach (['success', 'info', 'warning', 'error'] as $flashKey)
            @if (session($flashKey))
                <p
                    @class([
                        'm-0 mb-2 rounded-lg px-3 py-2 text-sm',
                        'border border-emerald-200 bg-emerald-50 text-emerald-950' => $flashKey === 'success',
                        'border border-sky-200 bg-sky-50 text-sky-950' => $flashKey === 'info',
                        'border border-amber-200 bg-amber-50 text-amber-950' => $flashKey === 'warning',
                        'border border-red-200 bg-red-50 text-red-950' => $flashKey === 'error',
                    ])
                    role="status"
                >{{ session($flashKey) }}</p>
            @endif
        @endforeach

        <div class="flex flex-wrap items-start gap-2 mb-2">
            <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="zatca" />
        </div>

        @if (filled($zatcaInlineError ?? null))
            <p class="m-0 mb-2 text-sm leading-snug text-red-800" data-testid="invoice-zatca-inline-error">{{ $zatcaInlineError }}</p>
        @endif

        @if ($canResubmitZatca ?? false)
            <x-invoices.zatca-resubmit-form
                :url="$zatcaResubmitUrl"
                :label="$zatcaResubmitLabel"
            />
        @endif
    </section>
@endif