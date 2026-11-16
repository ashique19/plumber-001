<header class="invoice-print-toolbar no-print flex flex-wrap gap-2 p-2">
    <button type="button" class="tap-action-primary min-h-11" onclick="window.print()">{{ __('pos.print') }}</button>
    @if ($receiptUrl ?? null)
        <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}">{{ __('invoices.layout_receipt') }}</a>
    @endif
    @if ($cashUrl ?? null)
        <a class="tap-action-muted min-h-11" href="{{ $cashUrl }}">{{ __('invoices.layout_cash') }}</a>
    @endif
    @if ($a4Url ?? null)
        <a class="tap-action-muted min-h-11" href="{{ $a4Url }}">{{ __('invoices.layout_a4') }}</a>
    @endif
</header>

@include('print.partials.not-cleared-watermark')

@if ($zatcaNotSent ?? false)
    <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
        {{ __('invoices.signing.zatca_not_sent') }}
    </p>
@endif