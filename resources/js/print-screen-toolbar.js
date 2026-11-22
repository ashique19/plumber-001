document.addEventListener('click', (event) => {
    const printBtn = event.target.closest('[data-print-trigger]');
    if (printBtn) {
        event.preventDefault();
        window.print();
        return;
    }

    const closeBtn = event.target.closest('[data-print-close]');
    if (! closeBtn) {
        return;
    }

    event.preventDefault();
    const fallback = closeBtn.getAttribute('data-close-fallback') || '/admin/invoices';
    window.close();
    // window.close() is a no-op when this tab was not opened by script.
    window.setTimeout(() => {
        if (! window.closed) {
            window.location.assign(fallback);
        }
    }, 150);
});
