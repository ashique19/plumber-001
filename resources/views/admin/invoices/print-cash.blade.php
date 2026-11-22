@php
    /** @var array<string, mixed> $t */
    $cash = \App\Support\Invoicing\InvoiceCashReceiptView::fromPrintData($t, $order ?? null);
    $tot = $t['totals'] ?? [];
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ $cash['titles']['en'] }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
        <style>
            @page { size: 80mm auto; margin: 0; }
            @media print {
                .invoice-print-root.invoice-print-sheet.invoice-print-sheet--cash {
                    width: 80mm !important;
                    max-width: 80mm !important;
                    min-width: 0 !important;
                    padding: 4mm !important;
                    zoom: normal !important;
                    transform: none !important;
                }
            }
        </style>
    @endpush

    @include('print.partials.toolbar-invoice', [
        'invoice' => $invoice,
        'receiptUrl' => $receiptUrl ?? null,
        'cashUrl' => $cashUrl ?? null,
        'a4Url' => $a4Url ?? null,
        'zatcaNotSent' => $zatcaNotSent ?? false,
        'fallbackBackUrl' => $fallbackBackUrl ?? route('admin.invoices'),
        'pdfUrl' => $pdfUrl ?? null,
        'canDownloadPdf' => $canDownloadPdf ?? true,
        'canResubmitZatca' => $canResubmitZatca ?? false,
        'zatcaResubmitUrl' => $zatcaResubmitUrl ?? null,
        'zatcaResubmitLabel' => $zatcaResubmitLabel ?? null,
    ])

    <div class="invoice-print-root invoice-print-sheet invoice-print-sheet--cash invoice-cash80" data-invoice-print data-auto-print="{{ request()->boolean('autoprint') ? '1' : '0' }}" data-document-number="{{ $t['document_number'] }}">

        <header class="invoice-cash80__seller">
            @if ($t['logo_url'])
                <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-cash80__logo">
            @endif
            <div class="invoice-cash80__seller-name">{{ $t['seller_en'] }}</div>
            <div class="invoice-cash80__seller-name invoice-cash80__seller-name--ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            @foreach ($cash['seller_en_lines'] as $line)
                <div class="invoice-cash80__seller-line">{{ $line }}</div>
            @endforeach
            @if ($cash['seller_ar_lines'] !== [])
                <div class="invoice-national-address-ar invoice-national-address-ar-lines invoice-cash80__seller-ar" lang="ar">
                    @foreach ($cash['seller_ar_lines'] as $line)
                        <div class="invoice-national-address-ar__line invoice-cash__seller-ar invoice-print-ar" dir="rtl">{!! $line !!}</div>
                    @endforeach
                </div>
            @endif
            @if ($t['trn'])
                <div class="invoice-cash80__row invoice-cash80__row--id">
                    <span class="invoice-cash80__label">VAT No.</span>
                    <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ $t['trn'] }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">الرقم الضريبي</span>
                </div>
            @endif
            @if ($t['cr_number'])
                <div class="invoice-cash80__row invoice-cash80__row--id">
                    <span class="invoice-cash80__label">CR No.</span>
                    <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ $t['cr_number'] }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">السجل التجاري</span>
                </div>
            @endif
        </header>

        @if (($t['show_qr'] ?? false) && (filled($t['qr_png'] ?? null) || filled($t['qr_tlv_base64'] ?? null)))
            <div class="invoice-cash80__qr">
                @if (filled($t['qr_png'] ?? null))
                    <img src="{{ $t['qr_png'] }}" alt="" width="200" height="200" class="invoice-cash80__qr-img">
                @else
                    <canvas id="receipt-qr" data-tlv="{{ $t['qr_tlv_base64'] }}" width="200" height="200"></canvas>
                @endif
            </div>
        @endif

        <h1 class="invoice-cash80__title">
            <span class="invoice-cash80__title-en">{{ $cash['titles']['en'] }}</span>
            <span class="invoice-cash80__title-ar invoice-print-ar" dir="rtl">{{ $cash['titles']['ar'] }}</span>
        </h1>

        <div class="invoice-cash80__rule" aria-hidden="true"></div>

        <section class="invoice-cash80__meta">
            <div class="invoice-cash80__row invoice-cash80__row--stack">
                <span class="invoice-cash80__label">Invoice No.</span>
                <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">رقم الفاتورة</span>
                <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ $t['document_number'] }}</span>
            </div>
            @if ($t['issue_datetime_print'] ?? $t['issue_date'] ?? null)
                <div class="invoice-cash80__row">
                    <span class="invoice-cash80__label">Date &amp; time</span>
                    <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ $t['issue_datetime_print'] ?? trim(($t['issue_date'] ?? '').' '.($t['issue_time'] ?? '')) }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">التاريخ والوقت</span>
                </div>
            @endif
            @if ($cash['customer_name'])
                <div class="invoice-cash80__row">
                    <span class="invoice-cash80__label">Customer</span>
                    <span class="invoice-cash80__value">{{ $cash['customer_name'] }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">العميل</span>
                </div>
            @endif
            @if ($cash['customer_phone'])
                <div class="invoice-cash80__row">
                    <span class="invoice-cash80__label">Phone</span>
                    <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ $cash['customer_phone'] }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">الجوال</span>
                </div>
            @endif
        </section>

        <div class="invoice-cash80__rule" aria-hidden="true"></div>

        <section class="invoice-cash80__lines" aria-label="Items">
            @foreach ($cash['lines'] as $line)
                <div class="invoice-cash80__item">
                    <div class="invoice-cash80__item-name">{{ $line['name_en'] }}</div>
                    @if ($line['name_ar'])
                        <div class="invoice-cash80__item-name invoice-cash80__item-name--ar invoice-print-ar" dir="rtl">{{ $line['name_ar'] }}</div>
                    @endif
                    <div class="invoice-cash80__item-row">
                        <span class="invoice-cash80__qty" dir="ltr">{{ $line['quantity_display'] }} x {{ number_format($line['unit_price'], 2) }} <span class="invoice-cash80__vat-rate">({{ number_format($line['vat_rate'], 0) }}%)</span></span>
                        <span class="invoice-cash80__item-amt" dir="ltr">{{ number_format($line['line_amount'], 2) }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="invoice-cash80__rule" aria-hidden="true"></div>

        <section class="invoice-cash80__totals">
            <div class="invoice-cash80__grand">
                <span class="invoice-cash80__grand-label">Total <span class="invoice-print-ar" dir="rtl">الإجمالي</span></span>
                <span class="invoice-cash80__grand-amt invoice-print-bidi-value" dir="ltr"><span class="invoice-sar-symbol">SAR</span> {{ number_format($cash['total_inc_vat'], 2) }}</span>
            </div>
            <div class="invoice-cash80__row invoice-cash80__row--plain">
                <span class="invoice-cash80__label">Items</span>
                <span class="invoice-cash80__value" dir="ltr">{{ $cash['item_count_display'] }}</span>
                <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">عدد الأصناف</span>
            </div>
            <div class="invoice-cash80__row invoice-cash80__row--plain">
                <span class="invoice-cash80__label">{{ $cash['payment_en'] }}</span>
                <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ number_format($cash['amount_paid'], 2) }}</span>
                <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">{{ $cash['payment_ar'] }}</span>
            </div>
            @if ($cash['change'] > 0)
                <div class="invoice-cash80__row invoice-cash80__row--plain">
                    <span class="invoice-cash80__label">Change</span>
                    <span class="invoice-cash80__value invoice-print-bidi-value" dir="ltr">{{ number_format($cash['change'], 2) }}</span>
                    <span class="invoice-cash80__label-ar invoice-print-ar" dir="rtl">الباقي</span>
                </div>
            @endif
        </section>

        <div class="invoice-cash80__rule" aria-hidden="true"></div>

        <section class="invoice-cash80__vat" aria-label="VAT summary">
            <div class="invoice-cash80__vat-heading">
                <span>Tax inclusive:</span>
                <span class="invoice-print-ar" dir="rtl">الضريبة شاملة</span>
            </div>
            <div class="invoice-cash80__vat-head">
                <span>VAT%</span>
                <span>Before VAT</span>
                <span>Incl. VAT</span>
                <span>VAT</span>
            </div>
            @foreach ($cash['vat_rows'] as $row)
                <div class="invoice-cash80__vat-row">
                    <span dir="ltr">{{ $row['vat_percent'] }}</span>
                    <span dir="ltr">{{ number_format($row['before_vat'], 2) }}</span>
                    <span dir="ltr">{{ number_format($row['incl_vat'], 2) }}</span>
                    <span dir="ltr">{{ number_format($row['vat'], 2) }}</span>
                </div>
            @endforeach
        </section>

        <div class="invoice-cash80__rule" aria-hidden="true"></div>

        <footer class="invoice-cash80__footer invoice-cash__footer">
            @if ($cash['cashier_name'])
                <div class="invoice-cash80__footer-line">Served by: {{ $cash['cashier_name'] }}</div>
            @endif
            @if ($cash['branch_name'])
                <div class="invoice-cash80__footer-line">{{ $cash['branch_name'] }}</div>
            @endif
            <div class="invoice-cash80__footer-meta">
                <div class="invoice-cash80__footer-when" dir="ltr">{{ $t['issue_date'] ?? '' }} {{ $t['issue_time'] ?? '' }}</div>
                <div class="invoice-cash80__footer-no" dir="ltr">{{ $t['document_number'] }}</div>
            </div>
            <div class="invoice-cash80__thanks">Thank you for shopping</div>
            <div class="invoice-cash80__thanks invoice-print-ar" dir="rtl">شكراً لتسوقكم</div>
            @if ($cash['barcode_value'] !== '')
                <svg id="receipt-barcode" class="invoice-cash80__barcode" data-value="{{ $cash['barcode_value'] }}" role="img" aria-label="{{ $cash['barcode_value'] }}"></svg>
            @endif
        </footer>
    </div>
</x-layouts.print>
