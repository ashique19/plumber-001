function embedUrl(url) {
    if (! url) {
        return '';
    }
    try {
        const parsed = new URL(url, window.location.origin);
        parsed.searchParams.set('embed', '1');

        return parsed.toString();
    } catch {
        const sep = url.includes('?') ? '&' : '?';

        return `${url}${sep}embed=1`;
    }
}

export function registerAdminPrintPreview(Alpine) {
    Alpine.data('adminPrintPreview', () => ({
        open: false,
        src: '',
        a4Url: null,
        cashUrl: null,
        pdfUrl: null,
        canDownloadPdf: true,
        layout: 'a4',
        backUrl: '',
        pdfBlockedTitle: '',
        _keyHandler: null,

        init() {
            this.pdfBlockedTitle = this.$el.dataset.pdfBlockedTitle || '';

            this._keyHandler = (event) => {
                if (event.key === 'Escape' && this.open) {
                    event.preventDefault();
                    this.close();
                }
            };
            document.addEventListener('keydown', this._keyHandler);

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-admin-print-open]');
                if (! trigger || trigger.hasAttribute('disabled') || trigger.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                event.preventDefault();
                this.openFromButton(trigger);
            });
        },

        destroy() {
            if (this._keyHandler) {
                document.removeEventListener('keydown', this._keyHandler);
            }
        },

        openFromButton(button) {
            this.openFromDetail({
                src: button.getAttribute('data-print-src') || '',
                a4Url: button.getAttribute('data-print-a4') || null,
                cashUrl: button.getAttribute('data-print-cash') || null,
                pdfUrl: button.getAttribute('data-print-pdf') || null,
                canDownloadPdf: button.getAttribute('data-print-can-download') !== '0',
                layout: button.getAttribute('data-print-layout') || 'a4',
                backUrl: button.getAttribute('data-print-back') || '',
            });
        },

        openFromDetail(detail) {
            if (! detail?.src) {
                return;
            }
            this.a4Url = detail.a4Url || null;
            this.cashUrl = detail.cashUrl || null;
            this.pdfUrl = detail.pdfUrl || null;
            this.canDownloadPdf = detail.canDownloadPdf !== false;
            this.layout = detail.layout || 'a4';
            this.backUrl = detail.backUrl || '';
            this.src = embedUrl(detail.src);
            this.open = true;
            document.body.classList.add('admin-print-preview-open');
        },

        close() {
            this.open = false;
            this.src = '';
            document.body.classList.remove('admin-print-preview-open');
        },

        switchLayout(next) {
            const target = next === 'a4' ? this.a4Url : this.cashUrl;
            if (! target) {
                return;
            }
            this.layout = next;
            this.src = embedUrl(target);
        },

        printFrame() {
            if (! this.canDownloadPdf) {
                return;
            }
            const frame = this.$refs.frame;
            if (frame?.contentWindow) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            }
        },

        get showLayoutSwitch() {
            return Boolean(this.a4Url) && Boolean(this.cashUrl);
        },
    }));
}