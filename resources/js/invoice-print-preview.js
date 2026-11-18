function estimatePrintPages(root) {
    if (! root) {
        return 1;
    }

    const mm = 96 / 25.4;
    const printableHeightPx = (297 - 28) * mm;
    const style = window.getComputedStyle(root);
    let height = root.scrollHeight;
    height -= parseFloat(style.paddingBottom) || 0;

    if (height <= 0 || printableHeightPx <= 0) {
        return 1;
    }

    return Math.max(1, Math.ceil(height / printableHeightPx));
}

function formatPageLabel(pageEnTpl, pageArTpl, doc, current, total) {
    const pageEn = pageEnTpl.replace(':current', String(current)).replace(':total', String(total));
    const pageAr = pageArTpl.replace(':current', String(current)).replace(':total', String(total));

    return `\u2066${pageEn}\u2069 · \u2067${pageAr}\u2069 — ${doc}`.trim();
}

function fillPrintPageLabels(isPrintEvent = false) {
    const root = document.querySelector('[data-invoice-print]');
    const el = root?.querySelector('[data-print-page-label]');
    if (! el) {
        return;
    }

    const doc = el.getAttribute('data-document-number') || '';
    const pageEnTpl = el.getAttribute('data-page-en') || '';
    const pageArTpl = el.getAttribute('data-page-ar') || '';

    if (! pageEnTpl.includes(':current') || ! pageArTpl.includes(':total')) {
        el.textContent = doc;

        return;
    }

    if (! isPrintEvent) {
        return;
    }

    const total = estimatePrintPages(root);
    el.textContent = formatPageLabel(pageEnTpl, pageArTpl, doc, 1, total);
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
    scaleClassicPreviewToViewport();
    window.addEventListener('resize', scaleClassicPreviewToViewport);
    window.addEventListener('beforeprint', () => fillPrintPageLabels(true));
});