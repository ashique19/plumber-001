@php
    $screenToolbar = ! ($isPdf ?? false) && ! request()->boolean('embed');
    $canDownloadPdf = (bool) ($canDownloadPdf ?? true);
    $pdfBlockedTitle = __('invoices.pdf_available_after_clearance');
    $closeFallbackUrl = $closeFallbackUrl ?? $fallbackBackUrl ?? route('admin.invoices');
    $backUrl = $fallbackBackUrl ?? route('admin.invoices');
    $backLabel = $backLabel ?? (($invoice ?? null) ? __('print.back_to_invoice') : __('print.back'));
    $currentPrintLayout = (string) request()->query('layout', '');
@endphp
@if ($screenToolbar)
    <header class="invoice-print-toolbar invoice-print-toolbar--screen no-print flex flex-wrap items-center gap-2 p-2" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-testid="print-screen-toolbar">
        <a
            href="{{ $backUrl }}"
            class="tap-action-muted min-h-11 inline-flex items-center"
            data-testid="print-screen-back"
        >{{ $backLabel }}</a>
        <button
            type="button"
            class="tap-action-primary min-h-11"
            data-print-trigger
            data-testid="print-screen-print-again"
        >{{ __('print.print_again') }}</button>
        <button
            type="button"
            class="tap-action-muted min-h-11"
            data-print-close
            data-close-fallback="{{ $closeFallbackUrl }}"
            data-testid="print-screen-close"
        >{{ __('print.close_window') }}</button>
        @if ($receiptUrl ?? null)
            <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}" data-testid="print-screen-layout-receipt">{{ __('invoices.layout_receipt') }}</a>
        @endif
        @if (($cashUrl ?? null) && $currentPrintLayout !== 'cash')
            <a class="tap-action-muted min-h-11" href="{{ $cashUrl }}" data-testid="print-screen-layout-cash">{{ __('print.layout_thermal') }}</a>
        @endif
        @if (($a4Url ?? null) && $currentPrintLayout !== 'a4')
            <a class="tap-action-muted min-h-11" href="{{ $a4Url }}" data-testid="print-screen-layout-a4">{{ __('print.layout_a4') }}</a>
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
                @if (filled($zatcaResubmitReturn ?? null))
                    <input type="hidden" name="return" value="{{ $zatcaResubmitReturn }}">
                @endif
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
