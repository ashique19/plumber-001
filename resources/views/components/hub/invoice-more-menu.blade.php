@props([
    'invoiceId' => null,
    'canResend' => false,
    'canRecord' => false,
    'more' => [],
])

<details class="hub-doc-table__more relative">
    <summary class="hub-doc-table__action hub-doc-table__action--more">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
        {{ __('invoices.action_more') }}
    </summary>
    <div class="hub-doc-table__more-panel" role="menu">
        @if ($canResend)
            <button
                type="button"
                class="hub-doc-table__more-item"
                role="menuitem"
                wire:click="resubmitInvoice({{ $invoiceId }})"
                wire:loading.attr="disabled"
            >
                {{ __('invoices.more_resend_zatca') }}
            </button>
        @endif
        @if ($canRecord && filled($more['record_payment'] ?? null))
            <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['record_payment'] }}">{{ __('invoices.more_record_payment') }}</a>
        @endif
        @if (filled($more['credit_note'] ?? null))
            <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['credit_note'] }}">{{ __('invoices.more_credit_note') }}</a>
        @endif
        @if (filled($more['duplicate'] ?? null))
            <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['duplicate'] }}">{{ __('invoices.more_duplicate') }}</a>
        @endif
    </div>
</details>