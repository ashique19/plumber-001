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

        @if (filled($zatcaResponseDisplay ?? null))
            <div class="mb-2 space-y-2 text-sm" data-testid="invoice-zatca-last-response">
                @if (filled($zatcaResponseDisplay['status'] ?? null))
                    <p class="m-0 text-[var(--app-muted)]">
                        <span class="font-medium text-[var(--app-text)]">{{ __('invoices.zatca_view_last_status') }}:</span>
                        <span dir="ltr">{{ $zatcaResponseDisplay['status'] }}</span>
                    </p>
                @endif
                @if (filled($zatcaResponseDisplay['submitted_at'] ?? null))
                    <p class="m-0 text-xs text-[var(--app-muted)]">
                        {{ __('invoices.zatca_view_last_submitted') }}:
                        <span dir="ltr">{{ $zatcaResponseDisplay['submitted_at'] }}</span>
                    </p>
                @endif
                @if (filled($zatcaResponseDisplay['request_id'] ?? null))
                    <p class="m-0 text-xs text-[var(--app-muted)]">
                        {{ __('invoices.zatca_view_request_id') }}:
                        <span dir="ltr">{{ $zatcaResponseDisplay['request_id'] }}</span>
                    </p>
                @endif
                @if (($zatcaResponseDisplay['messages'] ?? []) !== [])
                    <ul class="m-0 list-disc ps-5 text-[var(--app-text)]">
                        @foreach ($zatcaResponseDisplay['messages'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (($zatcaResponseDisplay['errors'] ?? []) !== [])
                    <div>
                        <p class="m-0 mb-1 font-medium text-red-800">{{ __('invoices.zatca_view_errors') }}</p>
                        <ul class="m-0 list-disc ps-5 text-red-900">
                            @foreach ($zatcaResponseDisplay['errors'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (($zatcaResponseDisplay['warnings'] ?? []) !== [])
                    <div>
                        <p class="m-0 mb-1 font-medium text-amber-900">{{ __('invoices.zatca_view_warnings') }}</p>
                        <ul class="m-0 list-disc ps-5 text-amber-950">
                            @foreach ($zatcaResponseDisplay['warnings'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        @if ($canResubmitZatca ?? false)
            <x-invoices.zatca-resubmit-form
                :url="$zatcaResubmitUrl"
                :label="$zatcaResubmitLabel"
            />
        @endif
    </section>
@endif