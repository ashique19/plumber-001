@php
    /** @var array<string, mixed> $t */
    $titles = \App\Support\Invoicing\DocumentPrintTitles::forContext($t);
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ $titles['en'] }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/css/print-template-modern.css', 'resources/js/receipt.js', 'resources/js/invoice-print-preview.js'])
    @endpush

    <div @class(['invoice-print-root', 'invoice-print-sheet', 'print-tpl-modern', 'invoice-print-root--pdf' => $isPdf ?? false]) data-invoice-print data-document-number="{{ $t['document_number'] }}" data-print-footer-doc="{{ $t['document_number'] }}">
        @include('print.partials.toolbar-invoice')

        <div class="print-tpl-modern__intro">
        <section class="invoice-a4__header print-tpl-modern__seller">
            <div class="invoice-a4__seller-side">
                <strong>{{ $t['seller_en'] }}</strong>
                @if ($t['address_en'])
                    <p class="m-0">{{ $t['address_en'] }}</p>
                @endif
                @if ($t['trn'])
                    <p class="m-0">VAT number {{ $t['trn'] }}</p>
                @endif
                @if ($t['unified_number'])
                    <p class="m-0">Unified number {{ $t['unified_number'] }}</p>
                @endif
            </div>
            <div>
                @if ($t['logo_url'])
                    <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo">
                @endif
            </div>
            <div class="invoice-a4__seller-side invoice-a4__seller-side--ar invoice-print-ar" dir="rtl">
                <strong>{{ $t['seller_ar'] }}</strong>
                @if ($t['address_ar'])
                    <p class="m-0 invoice-pdf-ar-plaintext" dir="rtl">{{ $t['address_ar'] }}</p>
                @endif
                @if ($t['trn'])
                    <p class="m-0 invoice-pdf-ar-plaintext" dir="rtl">رقم التسجيل الضريبي <span dir="ltr" class="invoice-pdf-ltr-digits">{{ $t['trn'] }}</span></p>
                @endif
                @if ($t['unified_number'])
                    <p class="m-0 invoice-pdf-ar-plaintext" dir="rtl">الرقم الموحد <span dir="ltr" class="invoice-pdf-ltr-digits">{{ $t['unified_number'] }}</span></p>
                @endif
            </div>
        </section>

        <hr class="print-tpl-modern__rule" />

        <x-invoices.print-document-title :t="$t" />

        @php
            $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
            $splitLeft = $isQuotation
                ? [
                    ['en' => 'Quotation number', 'ar' => 'رقم العرض', 'value' => $t['document_number'], 'nowrap' => true],
                    ['en' => 'Valid until', 'ar' => 'صالح حتى', 'value' => $t['validity_until'] ?? null],
                ]
                : [
                    ['en' => 'Invoice number', 'ar' => 'رقم الفاتورة', 'value' => $t['document_number'], 'nowrap' => true],
                    ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null],
                ];
        @endphp

        <x-invoices.print-buyer-table
            :rows="\App\Support\Invoicing\BuyerPrintBlock::rows($t['customer'] ?? null)"
            :split-left="$splitLeft"
            :split-right="[
                ['en' => 'Date', 'ar' => 'التاريخ', 'value' => $t['issue_date']],
                ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => $t['supply_date'] ?? null],
            ]"
        />
        </div>

        <table class="invoice-a4__table print-tpl-modern__lines">
            <thead>
                <tr>
                    <th>#<span class="ar invoice-print-ar" dir="rtl">م</span></th>
                    <th>Description<span class="ar invoice-print-ar" dir="rtl">الوصف</span></th>
                    <th>Qty<span class="ar invoice-print-ar" dir="rtl">الكمية</span></th>
                    <th>Price<span class="ar invoice-print-ar" dir="rtl">السعر</span></th>
                    <th>Taxable amount<span class="ar invoice-print-ar" dir="rtl">المبلغ الخاضع للضريبة</span></th>
                    <th>VAT amount (+%)<span class="ar invoice-print-ar" dir="rtl">القيمة المضافة</span></th>
                    <th>Line amount<span class="ar invoice-print-ar" dir="rtl">المجموع</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td>{{ $line['line_no'] }}</td>
                        <td>
                            <div class="invoice-a4__desc-en">{{ $line['name_en'] }}</div>
                            @if ($line['name_ar'])
                                <div class="invoice-a4__desc-ar invoice-print-ar" dir="rtl">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="tabular-nums print-tpl-modern__vat-cell">
                            <span><x-invoices.sar-symbol /> {{ number_format($line['vat_amount'], 2) }}</span>
                            <span class="print-tpl-modern__vat-pct">{{ number_format($line['vat_rate'], 0) }}%</span>
                        </td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <section class="print-tpl-modern__closing">
            @if (($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null))
                <div class="print-tpl-modern__qr-wrap">
                    <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :png="$t['qr_png'] ?? null" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
                </div>
            @else
                <div class="print-tpl-modern__qr-wrap print-tpl-modern__qr-wrap--empty" aria-hidden="true"></div>
            @endif
            @include('print.partials.modern-totals-table', ['tot' => $tot, 't' => $t])
        </section>

        <div class="invoice-print-words-block print-tpl-modern__words">
            @if (filled($t['amount_words_en'] ?? null))
                <p class="invoice-print-words-block__en invoice-a4__words">{{ $t['amount_words_en'] }}</p>
            @endif
            @if (filled($t['amount_words_ar'] ?? null))
                <p class="invoice-print-words-block__ar invoice-a4__words invoice-print-ar" dir="rtl">{{ $t['amount_words_ar'] }}</p>
            @endif
        </div>

        <footer class="invoice-a4__footer print-tpl-modern__sheet-footer print-only" data-document-number="{{ $t['document_number'] }}">
            <div class="print-tpl-modern__footer-names">
                <div>{{ $t['seller_en'] }}</div>
                <div class="invoice-a4__footer-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            </div>
            <x-invoices.print-page-label :document-number="$t['document_number']" class="print-tpl-modern__footer-page" />
        </footer>

        <footer class="print-tpl-modern__sheet-footer print-tpl-modern__sheet-footer--screen no-print" aria-hidden="true">
            <div class="print-tpl-modern__footer-names">
                <div>{{ $t['seller_en'] }}</div>
                <div class="invoice-a4__footer-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            </div>
            <x-invoices.print-page-label :document-number="$t['document_number']" class="print-tpl-modern__footer-page" />
        </footer>
    </div>
</x-layouts.print>