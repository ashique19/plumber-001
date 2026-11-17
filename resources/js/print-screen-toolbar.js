function navigatePrintBack(button) {
    const fallback = button?.getAttribute('data-fallback-url');
    if (window.history.length > 1) {
        window.history.back();

        return;
    }
    if (fallback) {
        window.location.href = fallback;
    }
}

document.addEventListener('click', (event) => {
    const back = event.target.closest('[data-print-back]');
    if (back) {
        event.preventDefault();
        navigatePrintBack(back);
    }

    const printBtn = event.target.closest('[data-print-trigger]');
    if (printBtn) {
        event.preventDefault();
        window.print();
    }
});