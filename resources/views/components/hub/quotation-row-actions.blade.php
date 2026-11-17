@props([
    'quotationId',
    'printUrl',
    'downloadUrl',
    'canManage' => false,
])

<div {{ $attributes->class(['hub-doc-table__actions', 'hub-doc-table__actions--compact']) }} data-testid="hub-quotation-row-actions">
    <x-hub.row-action-icon
        icon="print"
        :label="__('quotations.action_print')"
        :href="$printUrl"
        target="_blank"
        rel="noopener"
        test-id="hub-row-action-print"
    />
    <x-hub.row-action-icon
        icon="download"
        :label="__('quotations.action_download_pdf')"
        :href="$downloadUrl"
        test-id="hub-row-action-download"
    />
    @if ($canManage)
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
                <a class="hub-doc-table__more-item" role="menuitem" href="{{ route('admin.quotations.edit', $quotationId) }}">{{ __('quotations.edit') }}</a>
                <button
                    type="button"
                    class="hub-doc-table__more-item"
                    role="menuitem"
                    wire:click="convertToInvoice({{ $quotationId }})"
                >{{ __('quotations.convert') }}</button>
            </div>
        </details>
    @endif
</div>