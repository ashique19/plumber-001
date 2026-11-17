function estimatePrintPages(root) {
    if (! root) {
        return 0;
    }

    const mm = 96 / 25.4;
    const printableHeightPx = (297 - 28) * mm;
    const height = root.scrollHeight;
    if (height <= 0 || printableHeightPx <= 0) {
        return 0;
    }

    return Math.max(1, Math.ceil(height / printableHeightPx));
}

function fillPrintPageLabels() {
    document.querySelectorAll('[data-print-page-label]').forEach((el) => {
        const root = el.closest('[data-invoice-print]') || document.querySelector('[data-invoice-print]');
        const doc = el.getAttribute('data-document-number') || '';
        const template = el.getAttribute('data-page-of') || '';
        const pages = estimatePrintPages(root);

        if (pages === 1 && template.includes(':current') && template.includes(':total')) {
            el.textContent = `${template.replace(':current', '1').replace(':total', '1')} - ${doc}`.trim();

            return;
        }

        el.textContent = doc;
    });
}

function scaleClassicPreviewToViewport() {
    const root = document.querySelector('.invoice-print-root.print-tpl-classic');
    if (! root || window.matchMedia('print').matches) {
        return;
    }

    const targetWidth = Math.min(root.scrollWidth, 506);
    const viewport = Math.max(320, document.documentElement.clientWidth - 24);
    if (targetWidth <= viewport) {
        root.style.transform = '';
        root.style.width = '';
        return;
    }

    const scale = viewport / targetWidth;
    root.style.transform = `scale(${scale})`;
    root.style.transformOrigin = 'top center';
    root.style.width = `${targetWidth}px`;
    root.parentElement?.classList.add('invoice-print-preview-host');
}

document.addEventListener('DOMContentLoaded', () => {
    fillPrintPageLabels();
    scaleClassicPreviewToViewport();
    window.addEventListener('resize', scaleClassicPreviewToViewport);
    window.addEventListener('beforeprint', fillPrintPageLabels);
});
