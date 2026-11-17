@props([
    'quotationId',
    'printUrl',
    'downloadUrl',
    'printBackUrl' => '',
    'canManage' => false,
])

<div {{ $attributes->class(['hub-doc-table__actions', 'hub-doc-table__actions--compact']) }} data-testid="hub-quotation-row-actions">
    <x-hub.print-preview-trigger
        icon="print"
        :label="__('quotations.action_print')"
        :src="$printUrl"
        :pdf-url="$downloadUrl"
        layout="a4"
        :back-url="$printBackUrl"
        test-id="hub-row-action-print"
    />
    <x-hub.row-action-icon
        icon="download"
        :label="__('quotations.action_download_pdf')"
        :href="$downloadUrl"
        test-id="hub-row-action-download"
    />
    @if ($canManage)
        <x-hub.more-menu :menu-id="'quotation-'.$quotationId">
            <a class="hub-doc-table__more-item" role="menuitem" href="{{ route('admin.quotations.edit', $quotationId) }}">{{ __('quotations.edit') }}</a>
            <button
                type="button"
                class="hub-doc-table__more-item"
                role="menuitem"
                wire:click="convertToInvoice({{ $quotationId }})"
            >{{ __('quotations.convert') }}</button>
        </x-hub.more-menu>
    @endif
</div>