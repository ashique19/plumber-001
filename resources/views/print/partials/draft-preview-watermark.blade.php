@if ($t['draft_preview'] ?? false)
    <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-slate-300/80 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900" role="status">
        {{ __('invoices.print_draft_notice') }}
    </p>
    <div class="invoice-print-watermark invoice-print-watermark--draft" aria-hidden="true" data-testid="invoice-draft-watermark">
        {{ __('invoices.print_draft_watermark') }}
    </div>
@endif