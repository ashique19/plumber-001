@props([
    'invoiceId',
    'actions' => [],
    'more' => [],
    'viewUrl' => null,
    'downloadUrl' => null,
    'printUrl' => null,
    'canResubmitZatca' => false,
])

<div {{ $attributes->class(['hub-doc-table__actions', 'hub-doc-table__actions--compact']) }} data-testid="hub-invoice-row-actions">
    @if ($actions['view'] ?? false)
        <x-hub.row-action-icon
            icon="view"
            :label="__('invoices.action_view')"
            :href="$viewUrl"
            test-id="hub-row-action-view"
        />
    @endif
    @if ($actions['download'] ?? false)
        <x-hub.row-action-icon
            icon="download"
            :label="__('invoices.action_download_pdf')"
            :href="$downloadUrl"
            test-id="hub-row-action-download"
        />
        <x-hub.row-action-icon
            icon="print"
            :label="__('invoices.action_print')"
            :href="$printUrl"
            target="_blank"
            rel="noopener"
            test-id="hub-row-action-print"
        />
    @elseif ($actions['awaiting_clearance'] ?? false)
        <x-hub.row-action-icon
            icon="download"
            :label="__('invoices.action_download_pdf')"
            :disabled="true"
            :tooltip="__('invoices.awaiting_zatca_clearance')"
            test-id="hub-row-action-download-disabled"
        />
        <x-hub.row-action-icon
            icon="print"
            :label="__('invoices.action_print')"
            :disabled="true"
            :tooltip="__('invoices.awaiting_zatca_clearance')"
            test-id="hub-row-action-print-disabled"
        />
    @endif
    <x-hub.invoice-more-menu
        :invoice-id="$invoiceId"
        :actions="$actions"
        :more="$more"
        :can-resubmit-zatca="$canResubmitZatca"
    />
</div>