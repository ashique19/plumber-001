@php
    use App\Support\Hub\HubInvoiceStatusDisplay;
    /** @var \App\Models\Invoice $invoice */
@endphp

@if (! ($isPdf ?? false))
    <section
        class="invoice-view-status no-print mx-2 mb-2 rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-3"
        aria-label="{{ __('invoices.view_status_label') }}"
        data-testid="invoice-view-status"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="flex flex-wrap gap-2">
                <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="invoice" />
                <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="zatca" />
            </div>
            @if (HubInvoiceStatusDisplay::showsClearanceHint($invoice))
                <p class="invoice-view-status__hint m-0 text-xs leading-snug text-[var(--app-muted)] sm:flex-1 sm:min-w-[12rem]">
                    {{ HubInvoiceStatusDisplay::clearanceHint() }}
                </p>
            @endif
        </div>
    </section>
@endif