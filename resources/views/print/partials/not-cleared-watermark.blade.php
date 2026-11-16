@if ($t['print_watermark'] ?? false)
    <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
        {{ __('invoices.print_not_cleared_notice') }}
    </p>
    <div class="invoice-print-watermark print-only" aria-hidden="true">{{ __('invoices.print_not_cleared') }}</div>
@endif