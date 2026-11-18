@php
    /** @var \App\Models\Invoice $invoice */
    $display = $zatcaResponseDisplay ?? null;
    $detailLines = $display['field_errors'] ?? [];
    if ($detailLines === []) {
        $detailLines = $display['errors'] ?? [];
    }
    $hasFlashes = filled(session('success')) || filled(session('info')) || filled(session('warning')) || filled(session('error'));
    $hasUserFeedback = filled($zatcaInlineError ?? null)
        || filled($display['summary'] ?? null)
        || $detailLines !== []
        || ($display['warnings'] ?? []) !== []
        || ($display['messages'] ?? []) !== [];
    $showResubmitInPanel = ($canResubmitZatca ?? false) && request()->boolean('embed');
    $showPanel = ! ($isPdf ?? false)
        && $invoice->isIssued()
        && ($hasFlashes || $hasUserFeedback || $showResubmitInPanel);
@endphp

@if ($showPanel)
    <section
        class="invoice-view-zatca no-print mx-2 mb-2 rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-3"
        aria-label="{{ __('invoices.zatca_view_field_errors') }}"
        data-testid="invoice-view-zatca-panel"
    >
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
                    @if ($flashKey === 'error' || $flashKey === 'warning') style="white-space: pre-line" @endif
                >{{ session($flashKey) }}</p>
            @endif
        @endforeach

        @if ($hasUserFeedback)
            <div class="mb-2 space-y-2 text-sm" data-testid="invoice-zatca-user-feedback">
                @if (($display['messages'] ?? []) !== [])
                    <ul class="m-0 list-disc ps-5 text-[var(--app-text)]">
                        @foreach ($display['messages'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (filled($display['summary'] ?? null))
                    <p class="m-0 text-sm font-medium text-red-900" data-testid="invoice-zatca-error-summary">
                        {{ $display['summary'] }}
                    </p>
                @elseif (filled($zatcaInlineError ?? null))
                    <p class="m-0 text-sm font-medium text-red-900" data-testid="invoice-zatca-error-summary">
                        {{ $zatcaInlineError }}
                    </p>
                @endif
                @if ($detailLines !== [])
                    <div data-testid="invoice-zatca-field-errors">
                        <p class="m-0 mb-1 font-medium text-red-800">{{ __('invoices.zatca_view_field_errors') }}</p>
                        <ul class="m-0 list-disc ps-5 text-red-900">
                            @foreach ($detailLines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (($display['warnings'] ?? []) !== [])
                    <div data-testid="invoice-zatca-warnings">
                        <p class="m-0 mb-1 font-medium text-amber-900">{{ __('invoices.zatca_view_warnings') }}</p>
                        <ul class="m-0 list-disc ps-5 text-amber-950">
                            @foreach ($display['warnings'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        @if ($showResubmitInPanel)
            <x-invoices.zatca-resubmit-form
                :url="$zatcaResubmitUrl"
                :label="$zatcaResubmitLabel"
                :return-query="$zatcaResubmitReturn ?? null"
            />
        @endif
    </section>
@endif