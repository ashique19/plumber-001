const A4_REFERENCE_PX = 794;

function ensurePreviewHost(root) {
    const parent = root.parentElement;
    if (! parent) {
        return root;
    }
    parent.classList.add('invoice-print-preview-host');

    return parent;
}

function scalePrintSheetsToViewport() {
    if (window.matchMedia('print').matches) {
        return;
    }

    const sheets = document.querySelectorAll('.invoice-print-root.invoice-print-sheet');
    sheets.forEach((root) => {
        const host = ensurePreviewHost(root);
        root.style.boxSizing = 'border-box';
        root.style.width = `${A4_REFERENCE_PX}px`;
        root.style.minWidth = `${A4_REFERENCE_PX}px`;
        root.style.maxWidth = `${A4_REFERENCE_PX}px`;

        const hostWidth = Math.max(280, (host.clientWidth || document.documentElement.clientWidth) - 16);
        if (A4_REFERENCE_PX <= hostWidth) {
            root.style.transform = '';
            host.style.minHeight = '';

            return;
        }

        const scale = hostWidth / A4_REFERENCE_PX;
        root.style.transform = `scale(${scale})`;
        root.style.transformOrigin = 'top center';

        const layoutHeight = root.offsetHeight;
        host.style.minHeight = `${Math.ceil(layoutHeight * scale)}px`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    scalePrintSheetsToViewport();
    window.addEventListener('resize', scalePrintSheetsToViewport);
});