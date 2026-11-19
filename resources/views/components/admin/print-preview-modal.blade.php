<div
    class="admin-print-preview"
    x-data="adminPrintPreview"
    x-cloak
    x-show="open"
    x-trap.noscroll="open"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('print.preview_title') }}"
    data-testid="admin-print-preview"
    data-pdf-blocked-title="{{ __('invoices.pdf_available_after_clearance') }}"
    @if (config('locales.supported.'.app()->getLocale().'.dir', 'ltr') === 'rtl') dir="rtl" @endif
>
    <button
        type="button"
        class="admin-print-preview__close"
        @click="close()"
        data-testid="admin-print-preview-close"
        aria-label="{{ __('print.close') }}"
    >
        <x-app.icon name="x" class="admin-print-preview__close-icon" aria-hidden="true" />
    </button>
    <header class="admin-print-preview__bar">
        <div class="admin-print-preview__bar-actions">
            <div class="admin-print-preview__layout-toggle" x-show="showLayoutSwitch" x-cloak>
                <button type="button" :aria-pressed="layout === 'a4'" @click="switchLayout('a4')">{{ __('print.layout_a4') }}</button>
                <button type="button" :aria-pressed="layout === 'cash'" @click="switchLayout('cash')">{{ __('print.layout_thermal') }}</button>
            </div>
            <button
                type="button"
                class="tap-action-primary min-h-11 px-4"
                :class="{ 'opacity-60': ! canDownloadPdf }"
                :disabled="! canDownloadPdf"
                :title="canDownloadPdf ? null : pdfBlockedTitle"
                @click="printFrame()"
                data-testid="admin-print-preview-print"
            >{{ __('pos.print') }}</button>
            <a
                x-show="pdfUrl && canDownloadPdf"
                x-cloak
                :href="pdfUrl"
                class="tap-action-muted min-h-11 inline-flex items-center px-4"
                download
                data-testid="admin-print-preview-download"
            >{{ __('invoices.action_download_pdf') }}</a>
            <button
                type="button"
                x-show="pdfUrl && ! canDownloadPdf"
                x-cloak
                class="tap-action-muted min-h-11 px-4 opacity-60"
                disabled
                :title="pdfBlockedTitle"
                data-testid="admin-print-preview-download-disabled"
            >{{ __('invoices.action_download_pdf') }}</button>
        </div>
    </header>
    <div class="admin-print-preview__frame-wrap">
        <iframe
            x-ref="frame"
            class="admin-print-preview__frame"
            :src="open ? src : 'about:blank'"
            title="{{ __('print.preview_title') }}"
            data-testid="admin-print-preview-frame"
        ></iframe>
    </div>
</div>