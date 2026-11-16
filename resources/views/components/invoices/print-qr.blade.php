@props(['tlv', 'fallback' => false])

@if (filled($tlv))
    <div class="invoice-a4__qr">
        <canvas id="receipt-qr" data-tlv="{{ $tlv }}" width="180" height="180"></canvas>
        <p class="invoice-a4__qr-note">
            This QR code is encoded as per ZATCA e-invoicing requirements<br />
            <span dir="rtl">تم ترميز هذا الرمز وفقاً لمتطلبات هيئة الزكاة والضريبة والجمارك للفاتورة الإلكترونية</span>
        </p>
    </div>
@endif