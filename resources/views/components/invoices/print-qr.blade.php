@props(['tlv', 'fallback' => false, 'png' => null, 'splitCaptions' => false, 'isPdf' => false])

@if (filled($tlv) || filled($png))
    <div class="invoice-a4__qr">
        @if (filled($png))
            <img src="{{ $png }}" alt="" width="270" height="270" class="invoice-a4__qr-img">
        @else
            <canvas id="receipt-qr" data-tlv="{{ $tlv }}" width="270" height="270"></canvas>
        @endif
        @if ($splitCaptions)
            <div class="invoice-a4__qr-note-lines">
                @foreach (\App\Support\Invoicing\InvoicePrintQrCaption::enLines() as $line)
                    <p class="invoice-a4__qr-note-en m-0">{{ $line }}</p>
                @endforeach
                <div class="invoice-a4__qr-note-ar-lines" lang="ar">
                    @foreach (\App\Support\Invoicing\InvoicePrintQrCaption::arLines() as $line)
                        <div @class([
                            'invoice-a4__qr-note-ar__line',
                            'invoice-a4__qr-note-ar',
                            'invoice-pdf-ar-block' => $isPdf,
                        ]) dir="rtl" lang="ar">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="invoice-a4__qr-note-en">This QR code is encoded as per ZATCA e-invoicing requirements</p>
            <p class="invoice-a4__qr-note-ar invoice-pdf-ar-block" dir="rtl" lang="ar">تم ترميز هذا الرمز وفقاً لمتطلبات هيئة الزكاة والضريبة والجمارك للفاتورة الإلكترونية</p>
        @endif
    </div>
@endif