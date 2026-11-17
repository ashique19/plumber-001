@props([
    'invoiceId' => null,
    'actions' => [],
    'more' => [],
    'canResubmitZatca' => false,
])

@php
    $hasMenu = ($actions['record_payment'] ?? false)
        || filled($more['credit_note'] ?? null)
        || filled($more['duplicate'] ?? null)
        || ($actions['delete_draft'] ?? false)
        || ($actions['edit'] ?? false)
        || ($actions['issue_draft'] ?? false)
        || ($actions['preview_draft'] ?? false)
        || ($actions['authorize_draft'] ?? false)
        || (($actions['resend_zatca'] ?? false) && $canResubmitZatca);
@endphp

@if ($hasMenu)
    <details class="hub-doc-table__more relative">
        <summary
            class="hub-doc-table__action hub-doc-table__action--icon hub-doc-table__action--more"
            aria-label="{{ __('invoices.action_more') }}"
            title="{{ __('invoices.action_more') }}"
            data-testid="hub-row-action-more"
        >
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
            <span class="sr-only">{{ __('invoices.action_more') }}</span>
        </summary>
        <div class="hub-doc-table__more-panel" role="menu">
            @if ($actions['preview_draft'] ?? false && filled($actions['preview_draft_url'] ?? null))
                <a
                    class="hub-doc-table__more-item"
                    role="menuitem"
                    href="{{ $actions['preview_draft_url'] }}"
                    target="_blank"
                    rel="noopener"
                    data-testid="hub-draft-action-preview"
                >{{ __('invoices.action_preview_draft') }}</a>
            @endif
            @if ($actions['authorize_draft'] ?? false)
                <button
                    type="button"
                    class="hub-doc-table__more-item hub-doc-table__more-item--accent"
                    role="menuitem"
                    wire:click="authorizeDraft({{ $invoiceId }})"
                    wire:confirm="{{ __('invoices.authorize_draft_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="authorizeDraft({{ $invoiceId }})"
                    data-testid="hub-draft-action-authorize"
                >
                    {{ __('invoices.action_authorize_draft') }}
                </button>
            @endif
            @if ($actions['issue_draft'] ?? false)
                <button
                    type="button"
                    class="hub-doc-table__more-item hub-doc-table__more-item--accent"
                    role="menuitem"
                    wire:click="issueDraft({{ $invoiceId }})"
                    wire:confirm="{{ __('invoices.issue_draft_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="issueDraft({{ $invoiceId }})"
                    data-testid="hub-draft-action-issue"
                >
                    {{ __('invoices.action_issue_draft') }}
                </button>
            @endif
            @if ($actions['edit'] ?? false)
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ $actions['edit_url'] }}" data-testid="hub-draft-action-edit">{{ __('invoices.action_edit') }}</a>
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
            @if (($actions['resend_zatca'] ?? false) && $canResubmitZatca)
                <button
                    type="button"
                    class="hub-doc-table__more-item hub-doc-table__more-item--accent"
                    role="menuitem"
                    wire:click="resubmitInvoice({{ $invoiceId }})"
                    wire:loading.attr="disabled"
                    wire:target="resubmitInvoice({{ $invoiceId }})"
                >
                    {{ $actions['resend_zatca_label'] ?? __('invoices.action_resend_zatca') }}
                </button>
            @endif
            @if ($actions['delete_draft'] ?? false)
                <button
                    type="button"
                    class="hub-doc-table__more-item text-red-700"
                    role="menuitem"
                    wire:click="deleteDraft({{ $invoiceId }})"
                    wire:confirm="{{ __('invoices.delete_draft_confirm') }}"
                    data-testid="hub-draft-action-delete"
                >
                    {{ __('invoices.action_delete_draft') }}
                </button>
            @endif
        </div>
    </details>
@endif