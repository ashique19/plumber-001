@php
    /** @var array<string, mixed> $t */
    $tClassic = array_merge($t, ['classic_title_stack' => true]);
    $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
    $metaRows = array_values(array_filter([
        ['en' => 'Invoice number', 'ar' => 'رقم الفاتورة', 'value' => $isQuotation ? null : $t['document_number']],
        ['en' => 'Quotation number', 'ar' => 'رقم العرض', 'value' => $isQuotation ? $t['document_number'] : null],
        ['en' => 'Invoice Issue Date', 'ar' => 'تاريخ إصدار الفاتورة', 'value' => $t['issue_datetime'] ?? $t['issue_date']],
        ['en' => 'Due Date', 'ar' => 'تاريخ الاستحقاق', 'value' => $isQuotation ? null : ($t['due_date'] ?? null)],
        ['en' => 'Valid until', 'ar' => 'صالح حتى', 'value' => $isQuotation ? ($t['validity_until'] ?? null) : null],
        ['en' => 'Terms', 'ar' => 'شروط', 'value' => $isQuotation ? 'Quotation' : 'SAR '.($t['payment_display_en'] ?? 'Cash')],
    ], fn (array $row): bool => filled($row['value'] ?? null)));
    $sellerRows = \App\Support\Invoicing\ClassicPartyPrintBlock::sellerRows($t);
    $buyerRows = \App\Support\Invoicing\ClassicPartyPrintBlock::buyerRows($t['customer'] ?? null);
    $tot = $t['totals'];
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ \App\Support\Invoicing\DocumentPrintTitles::forContext($t)['en'] }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/css/print-template-classic.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet print-tpl-classic" data-invoice-print data-document-number="{{ $t['document_number'] }}">
        @include('print.partials.toolbar-invoice')

        <header class="print-classic__top">
            @if ($t['logo_url'])
                <img src="{{ asset($t['logo_url']) }}" alt="" class="print-classic__logo">
            @endif
            <div class="print-classic__top-center">
                <x-invoices.print-document-title :t="$tClassic" />
            </div>
            <div class="print-classic__top-right">
                <div class="print-classic__page-label">Page: <span class="print-classic__page-counter"></span></div>
                @if (($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null))
                    <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
                @endif
            </div>
        </header>

        <table class="print-classic__meta-table">
            <tbody>
                @foreach ($metaRows as $row)
                    <tr>
                        <td class="print-classic__cell-label">{{ $row['en'] }}</td>
                        <td class="print-classic__cell-value">{{ $row['value'] }}</td>
                        <td class="print-classic__cell-label ar" dir="rtl">{{ $row['ar'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="print-classic__parties">
            <div class="print-classic__party">
                <div class="print-classic__party-head">Seller <span class="ar" dir="rtl">المورد</span></div>
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
                                <td class="print-classic__cell-label ar" dir="rtl">{{ $row['ar'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="print-classic__party">
                <div class="print-classic__party-head">Buyer <span class="ar" dir="rtl">العميل</span></div>
                <table class="print-classic__party-table">
                    <tbody>
                        @forelse ($buyerRows as $row)
                            <tr>
                                <td class="print-classic__cell-label">{{ $row['en'] }}</td>
                                <td class="print-classic__cell-value" @if (! empty($row['html'])) dir="auto" @endif>
                                    @if (! empty($row['html']))
                                        {!! $row['html'] !!}
                                    @else
                                        {{ $row['value'] ?? '' }}
                                    @endif
                                </td>
                                <td class="print-classic__cell-label ar" dir="rtl">{{ $row['ar'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="print-classic__cell-value">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <table class="print-classic__lines">
            <thead>
                <tr>
                    <th>Nature of goods or services<span class="ar">تفاصيل السلع أو الخدمات</span></th>
                    <th>Line Num<span class="ar">السطر</span></th>
                    <th>Unit Price<span class="ar">سعر الوحدة</span></th>
                    <th>Quantity<span class="ar">الكمية</span></th>
                    <th>Taxable Amount<span class="ar">المبلغ الخاضع للضريبة</span></th>
                    <th>Discount<span class="ar">خصومات</span></th>
                    <th>Tax Rate<span class="ar">نسبة الضريبة</span></th>
                    <th>Tax Amount<span class="ar">مبلغ الضريبة</span></th>
                    <th>Item Subtotal (Including VAT)<span class="ar">المجموع شامل ضريبة القيمة المضافة</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td class="print-classic__desc">
                            <div>{{ $line['name_en'] }}</div>
                            @if ($line['name_ar'])
                                <div dir="rtl">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td>{{ $line['line_no'] }}</td>
                        <td class="tabular-nums">{{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="tabular-nums">{{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['discount_ex_vat'] ?? 0, 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['vat_rate'], 0) }} %</td>
                        <td class="tabular-nums">{{ number_format($line['vat_amount'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="print-classic__totals-wrap">
            <table class="print-classic__totals">
                <tr>
                    <td class="print-classic__cell-label"><span dir="rtl">الإجمالي (غير شامل ضريبة القيمة المضافة)</span> Total Excluding Vat</td>
                    <td class="print-classic__cell-value tabular-nums">{{ number_format($tot['subtotal_ex_vat'], 2) }} SAR</td>
                </tr>
                <tr>
                    <td class="print-classic__cell-label"><span dir="rtl">الإجمالي الخاضع للضريبة</span> Total Taxable Amount</td>
                    <td class="print-classic__cell-value tabular-nums">{{ number_format($tot['subtotal_ex_vat'] - ($tot['invoice_discount_ex_vat'] ?? 0), 2) }} SAR</td>
                </tr>
                <tr>
                    <td class="print-classic__cell-label"><span dir="rtl">مجموع ضريبة القيمة المضافة</span> Total Vat</td>
                    <td class="print-classic__cell-value tabular-nums">{{ number_format($tot['total_vat'], 2) }} SAR</td>
                </tr>
                <tr>
                    <td class="print-classic__cell-label"><span dir="rtl">اجمالي المبلغ المستحق</span> Total Amount Due</td>
                    <td class="print-classic__cell-value tabular-nums"><strong>{{ number_format($tot['net_due_inc_vat'] ?? $tot['total_inc_vat'], 2) }} SAR</strong></td>
                </tr>
            </table>
        </div>

        <p class="print-classic__words" dir="rtl">{{ $t['amount_words_ar'] }}</p>
    </div>
</x-layouts.print>