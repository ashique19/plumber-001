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
});