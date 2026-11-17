@props([
    'invoiceId' => null,
    'actions' => [],
    'more' => [],
])

@php
    $hasMenu = ($actions['record_payment'] ?? false)
        || filled($more['credit_note'] ?? null)
        || filled($more['duplicate'] ?? null)
        || ($actions['delete_draft'] ?? false)
        || ($actions['edit'] ?? false);
@endphp

@if ($hasMenu)
    <details class="hub-doc-table__more relative">
        <summary class="hub-doc-table__action hub-doc-table__action--more">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
            {{ __('invoices.action_more') }}
        </summary>
        <div class="hub-doc-table__more-panel" role="menu">
            @if ($actions['edit'] ?? false)
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ $actions['edit_url'] }}">{{ __('invoices.action_edit') }}</a>
            @endif
            @if (($actions['record_payment'] ?? false) && filled($more['record_payment'] ?? null))
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['record_payment'] }}">{{ __('invoices.more_record_payment') }}</a>
            @endif
            @if (filled($more['credit_note'] ?? null))
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['credit_note'] }}">{{ __('invoices.more_credit_note') }}</a>
            @endif
            @if (filled($more['duplicate'] ?? null))
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ $more['duplicate'] }}">{{ __('invoices.more_duplicate') }}</a>
            @endif
            @if ($actions['delete_draft'] ?? false)
                <button
                    type="button"
                    class="hub-doc-table__more-item text-red-700"
                    role="menuitem"
                    wire:click="deleteDraft({{ $invoiceId }})"
                    wire:confirm="{{ __('invoices.delete_draft_confirm') }}"
                >
                    {{ __('invoices.action_delete_draft') }}
                </button>
            @endif
        </div>
    </details>
@endif