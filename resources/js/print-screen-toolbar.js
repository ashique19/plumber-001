document.addEventListener('click', (event) => {
    const printBtn = event.target.closest('[data-print-trigger]');
    if (printBtn) {
        event.preventDefault();
        window.print();
    }
});