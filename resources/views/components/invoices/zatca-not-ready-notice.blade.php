@props(['show' => false])

@if ($show)
    <p
        class="invoice-zatca-notice rounded-2xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-100"
        role="status"
    >
        {{ __('invoices.zatca_not_ready_notice') }}
    </p>
@endif