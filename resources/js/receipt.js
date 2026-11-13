import JsBarcode from 'jsbarcode';
import QRCode from 'qrcode';

function renderReceiptQr() {
    const canvas = document.getElementById('receipt-qr');
    if (! canvas) {
        return;
    }

    const payload = canvas.dataset.tlv;
    if (! payload) {
        return;
    }

    QRCode.toCanvas(canvas, payload, {
        errorCorrectionLevel: 'M',
        margin: 1,
        width: 240,
        color: {
            dark: '#000000',
            light: '#ffffff',
        },
    }).catch((error) => {
        console.error('receipt-qr', error);
    });
}

function renderReceiptBarcode() {
    const svg = document.getElementById('receipt-barcode');
    if (! svg) {
        return;
    }

    const value = svg.dataset.value;
    if (! value) {
        return;
    }

    try {
        JsBarcode(svg, value, {
            format: 'CODE128',
            displayValue: true,
            font: 'monospace',
            fontSize: 12,
            textMargin: 2,
            margin: 4,
            height: 48,
            width: 1.6,
        });
    } catch (error) {
        console.error('receipt-barcode', error);
    }
}

function receiptRoot() {
    return document.querySelector('[data-receipt-root]');
}

function maybeAutoPrint() {
    const root = receiptRoot();
    if (root?.dataset.autoPrint !== '1') {
        return;
    }

    window.setTimeout(() => window.print(), 400);
}

document.addEventListener('DOMContentLoaded', () => {
    renderReceiptQr();
    renderReceiptBarcode();
    maybeAutoPrint();
});
