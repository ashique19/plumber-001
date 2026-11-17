@php
    $screenToolbar = ! ($isPdf ?? false) && ! request()->boolean('embed');
    $canDownloadPdf = (bool) ($canDownloadPdf ?? true);
    $pdfBlockedTitle = __('invoices.pdf_available_after_clearance');
@endphp
@if ($screenToolbar)
    <header class="invoice-print-toolbar invoice-print-toolbar--screen no-print flex flex-wrap items-center gap-2 p-2" data-testid="print-screen-toolbar">
        <button
            type="button"
            class="tap-action-muted min-h-11"
            data-print-back
            data-fallback-url="{{ $fallbackBackUrl ?? route('admin.invoices') }}"
        >{{ __('print.back') }}</button>
        @if ($canDownloadPdf)
            <button type="button" class="tap-action-primary min-h-11" data-print-trigger>{{ __('pos.print') }}</button>
        @else
            <button
                type="button"
                class="tap-action-primary min-h-11 opacity-60"
                disabled
                title="{{ $pdfBlockedTitle }}"
                data-testid="print-toolbar-print-disabled"
            >{{ __('pos.print') }}</button>
        @endif
        @if ($receiptUrl ?? null)
            <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}">{{ __('invoices.layout_receipt') }}</a>
        @endif
        @php
            $currentPrintLayout = (string) request()->query('layout', '');
        @endphp
        @if (($cashUrl ?? null) && $currentPrintLayout !== 'cash')
            <a class="tap-action-muted min-h-11" href="{{ $cashUrl }}">{{ __('print.layout_thermal') }}</a>
        @endif
        @if (($a4Url ?? null) && $currentPrintLayout !== 'a4')
            <a class="tap-action-muted min-h-11" href="{{ $a4Url }}">{{ __('print.layout_a4') }}</a>
        @endif
        @if ($canDownloadPdf && ($pdfUrl ?? null))
            <a
                class="tap-action-muted min-h-11"
                href="{{ $pdfUrl }}"
                download
                data-testid="print-toolbar-download-pdf"
            >{{ __('invoices.action_download_pdf') }}</a>
        @elseif ($pdfUrl ?? null)
            <button
                type="button"
                class="tap-action-muted min-h-11 opacity-60"
                disabled
                title="{{ $pdfBlockedTitle }}"
                data-testid="print-toolbar-download-pdf-disabled"
            >{{ __('invoices.action_download_pdf') }}</button>
        @endif
        @if ($canResubmitZatca ?? false)
            <form
                method="post"
                action="{{ $zatcaResubmitUrl }}"
                class="inline"
                data-testid="invoice-toolbar-zatca-resubmit"
                onsubmit="const btn=this.querySelector('button[type=submit]');if(btn){btn.disabled=true;}"
            >
                @csrf
                <button type="submit" class="tap-action-primary min-h-11">{{ $zatcaResubmitLabel }}</button>
            </form>
        @endif
    </header>
    @vite('resources/js/print-screen-toolbar.js')
@endif

@if ($invoice ?? null)
    @include('print.partials.invoice-draft-lifecycle', ['invoice' => $invoice])
    @include('print.partials.invoice-view-status', ['invoice' => $invoice])
    @include('print.partials.invoice-view-zatca', ['invoice' => $invoice])
@endif

@include('print.partials.draft-preview-watermark')
@include('print.partials.not-cleared-watermark')

@if ($zatcaNotSent ?? false)
    <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
        {{ __('invoices.signing.zatca_not_sent') }}
    </p>
@endif