@props(['tlv', 'fallback' => false, 'png' => null])

@if (filled($tlv) || filled($png))
    <div class="invoice-a4__qr">
        @if (filled($png))
            <img src="{{ $png }}" alt="" width="180" height="180" class="invoice-a4__qr-img">
        @else
            <canvas id="receipt-qr" data-tlv="{{ $tlv }}" width="180" height="180"></canvas>
        @endif
        <p class="invoice-a4__qr-note">
            This QR code is encoded as per ZATCA e-invoicing requirements<br />
            <span dir="rtl">تم ترميز هذا الرمز وفقاً لمتطلبات هيئة الزكاة والضريبة والجمارك للفاتورة الإلكترونية</span>
        </p>
    </div>
@endif