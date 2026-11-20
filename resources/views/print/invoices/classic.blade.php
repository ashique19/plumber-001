@php
    /** @var array<string, mixed> $t */
    $tClassic = array_merge($t, ['classic_title_stack' => true]);
    $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
    $introRows = $isQuotation
        ? \App\Support\Invoicing\InvoicePrintMetaRows::modernQuotationMeta($t)
        : \App\Support\Invoicing\InvoicePrintMetaRows::modernIntroGrid($t);
    $sellerRows = \App\Support\Invoicing\ClassicPartyPrintBlock::sellerRows($t);
    $buyerRows = \App\Support\Invoicing\ClassicPartyPrintBlock::buyerRows($t['customer'] ?? null, $isPdf ?? false);
    $tot = $t['totals'];
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ \App\Support\Invoicing\DocumentPrintTitles::forContext($t)['en'] }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/css/print-template-classic.css', 'resources/js/receipt.js', 'resources/js/invoice-print-preview.js'])
    @endpush

    <div @class(['invoice-print-root', 'invoice-print-sheet', 'print-tpl-classic', 'invoice-print-root--pdf' => $isPdf ?? false]) data-invoice-print data-document-number="{{ $t['document_number'] }}">
        @include('print.partials.toolbar-invoice')

        @include('print.partials.seller-header-with-ids', ['t' => $t, 'isPdf' => $isPdf ?? false])
        <hr class="print-tpl-modern__rule" />

        <header class="print-classic__top">
            <div></div>
            <div class="print-classic__top-center">
                @if ($t['logo_url'])
                    <img src="{{ asset($t['logo_url']) }}" alt="" class="print-classic__logo">
                @endif
                <x-invoices.print-document-title :t="$tClassic" />
            </div>
            <div class="print-classic__top-right">
                <div class="print-classic__page-label">
                    <x-invoices.print-page-label :document-number="$t['document_number']" />
                </div>
                @if (($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null))
                    <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :png="$t['qr_png'] ?? null" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
                @endif
            </div>
        </header>

        <x-invoices.print-info-grid :rows="$introRows" class="print-classic__intro-grid" />

        <div class="print-classic__parties">
            <div class="print-classic__party">
                <div class="print-classic__party-head">Seller <span class="ar invoice-print-ar" dir="rtl">المورد</span></div>
                <table class="print-classic__party-table">
                    <tbody>
                        @foreach ($sellerRows as $row)
                            <tr>
                                <td class="print-classic__cell-label">{{ $row['en'] }}</td>
                                <td class="print-classic__cell-value" @if (! empty($row['html'])) dir="auto" @endif>
                                    @if (! empty($row['html']))
                                        {!! $row['html'] !!}
                                    @else
                                        {{ $row['value'] ?? '' }}
                                    @endif
                                </td>
                                <td class="print-classic__cell-label ar invoice-print-ar" dir="rtl">{{ $row['ar'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($buyerRows) > 0)
                <div class="print-classic__party">
                    <div class="print-classic__party-head">Buyer <span class="ar invoice-print-ar" dir="rtl">العميل</span></div>
                    <table class="print-classic__party-table">
                        <tbody>
                            @foreach ($buyerRows as $row)
                                <tr>
                                    <td class="print-classic__cell-label">{{ $row['en'] }}</td>
                                    <td class="print-classic__cell-value" @if (! empty($row['html'])) dir="auto" @endif>
                                        @if (! empty($row['html']))
                                            {!! $row['html'] !!}
                                        @else
                                            {{ $row['value'] ?? '' }}
                                        @endif
                                    </td>
                                    <td class="print-classic__cell-label ar invoice-print-ar" dir="rtl">{{ $row['ar'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <table class="print-classic__lines">
            <thead>
                <tr>
                    <th>Nature of goods or services<span class="ar invoice-print-ar" dir="rtl">تفاصيل السلع أو الخدمات</span></th>
                    <th>Line Num<span class="ar invoice-print-ar" dir="rtl">السطر</span></th>
                    <th>Unit Price<span class="ar invoice-print-ar" dir="rtl">سعر الوحدة</span></th>
                    <th>Quantity<span class="ar invoice-print-ar" dir="rtl">الكمية</span></th>
                    <th>Taxable Amount<span class="ar invoice-print-ar" dir="rtl">المبلغ الخاضع للضريبة</span></th>
                    <th>Discount<span class="ar invoice-print-ar" dir="rtl">خصومات</span></th>
                    <th>Tax Rate<span class="ar invoice-print-ar" dir="rtl">نسبة الضريبة</span></th>
                    <th>Tax Amount<span class="ar invoice-print-ar" dir="rtl">مبلغ الضريبة</span></th>
                    <th>Item Subtotal (Including VAT)<span class="ar invoice-print-ar" dir="rtl">المجموع شامل ضريبة القيمة المضافة</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td class="print-classic__desc">
                            <div>{{ $line['name_en'] }}</div>
                            @if ($line['name_ar'])
                                <div class="invoice-print-ar" dir="rtl">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="col-index tabular-nums">{{ $line['line_no'] }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="col-num tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['discount_ex_vat'] ?? 0, 2) }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['vat_rate'], 0) }} %</td>
                        <td class="col-num tabular-nums">{{ number_format($line['vat_amount'], 2) }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="print-classic__totals-wrap">
            <table class="print-classic__totals invoice-print-totals">
                <x-invoices.print-totals-row en="Total Excluding Vat" ar="الإجمالي (غير شامل ضريبة القيمة المضافة)">
                    <x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'], 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="Total Taxable Amount" ar="الإجمالي الخاضع للضريبة">
                    <x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'] - ($tot['invoice_discount_ex_vat'] ?? 0), 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="Total Vat" ar="مجموع ضريبة القيمة المضافة">
                    <x-invoices.sar-symbol /> {{ number_format($tot['total_vat'], 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="Total Amount Due" ar="اجمالي المبلغ المستحق" :strong="true">
                    <x-invoices.sar-symbol /> {{ number_format($tot['net_due_inc_vat'] ?? $tot['total_inc_vat'], 2) }}
                </x-invoices.print-totals-row>
            </table>
        </div>

        <div class="invoice-print-words-block">
            <x-invoices.print-amount-words-en
                :text="$t['amount_words_en'] ?? null"
                :is-pdf="$isPdf ?? false"
                class="invoice-print-words-block__en"
            />
            <x-invoices.print-amount-words-ar
                :text="$t['amount_words_ar'] ?? null"
                :is-pdf="$isPdf ?? false"
                class="invoice-print-words-block__ar invoice-print-ar"
            />
        </div>

        <footer class="print-classic__sheet-footer">
            <x-invoices.print-page-label :document-number="$t['document_number']" />
        </footer>
    </div>
</x-layouts.print>