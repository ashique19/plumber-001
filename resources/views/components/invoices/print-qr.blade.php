@props(['tlv', 'fallback' => false])

@if (filled($tlv))
    <div class="invoice-a4__qr">
        <canvas id="receipt-qr" data-tlv="{{ $tlv }}" width="180" height="180"></canvas>
        <p class="invoice-a4__qr-note">
            @if ($fallback)
                Phase-1 QR · Scan seller VAT &amp; totals / امسح للتحقق من البائع والضريبة والإجمالي
            @else
                ZATCA QR · Signed invoice / فاتورة موقّعة
            @endif
        </p>
    </div>
@endif