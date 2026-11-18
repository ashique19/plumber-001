const A4_REFERENCE_PX = 794;

function ensurePreviewHost(root) {
    const parent = root.parentElement;
    if (! parent) {
        return root;
    }
    parent.classList.add('invoice-print-preview-host');

    return parent;
}

function ensureScaleWrap(root) {
    let wrap = root.parentElement;
    if (! wrap?.classList?.contains('invoice-print-preview-scale')) {
        wrap = document.createElement('div');
        wrap.className = 'invoice-print-preview-scale';
        root.parentNode?.insertBefore(wrap, root);
        wrap.appendChild(root);
    }

    return wrap;
}

function resetSheetLayout(root, wrap, host) {
    root.style.transform = '';
    root.style.zoom = '';
    wrap.style.width = '';
    wrap.style.height = '';
    wrap.style.overflow = '';
    wrap.style.marginInline = '';
    host.style.minHeight = '';
    host.style.height = '';
}

function applySheetScale(root, wrap, host, scale) {
    root.style.boxSizing = 'border-box';
    root.style.width = `${A4_REFERENCE_PX}px`;
    root.style.minWidth = `${A4_REFERENCE_PX}px`;
    root.style.maxWidth = `${A4_REFERENCE_PX}px`;

    const layoutHeight = root.offsetHeight;
    const scaledWidth = Math.ceil(A4_REFERENCE_PX * scale);
    const scaledHeight = Math.ceil(layoutHeight * scale);

    wrap.style.width = `${scaledWidth}px`;
    wrap.style.height = `${scaledHeight}px`;
    wrap.style.overflow = 'hidden';
    wrap.style.marginInline = 'auto';
    host.style.minHeight = `${scaledHeight}px`;
    host.style.height = `${scaledHeight}px`;

    if (typeof CSS !== 'undefined' && CSS.supports('zoom', '1')) {
        root.style.zoom = String(scale);
        root.style.transform = '';
        root.style.transformOrigin = '';
    } else {
        root.style.zoom = '';
        root.style.transform = `scale(${scale})`;
        root.style.transformOrigin = 'top center';
    }
}

function scalePrintSheetsToViewport() {
    if (window.matchMedia('print').matches) {
        return;
    }

    const sheets = document.querySelectorAll('.invoice-print-root.invoice-print-sheet');
    sheets.forEach((root) => {
        const wrap = ensureScaleWrap(root);
        const host = ensurePreviewHost(wrap);

        const hostWidth = Math.max(280, (host.clientWidth || document.documentElement.clientWidth) - 16);
        if (A4_REFERENCE_PX <= hostWidth) {
            resetSheetLayout(root, wrap, host);

            return;
        }

        const scale = hostWidth / A4_REFERENCE_PX;
        applySheetScale(root, wrap, host, scale);
    });
}

function observePrintSheets() {
    if (typeof ResizeObserver === 'undefined') {
        return;
    }

    const observer = new ResizeObserver(() => scalePrintSheetsToViewport());
    document.querySelectorAll('.invoice-print-root.invoice-print-sheet').forEach((root) => {
        observer.observe(root);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    scalePrintSheetsToViewport();
    observePrintSheets();
    window.addEventListener('resize', scalePrintSheetsToViewport);
});