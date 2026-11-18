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
        @include('print.partials.seller-header-with-ids', ['t' => $t])
        <hr class="print-tpl-modern__rule" />

        <x-invoices.print-document-title :t="$t" />

        @php
            $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
            $splitLeft = $isQuotation
                ? [
                    ['en' => 'Quotation number', 'ar' => 'رقم العرض', 'value' => $t['document_number'], 'nowrap' => true],
                    ['en' => 'Payment terms', 'ar' => 'شروط الدفع', 'value' => $t['payment_terms_en'] ?? null, 'value_ar' => $t['payment_terms_ar'] ?? null],
                    ['en' => 'Valid until', 'ar' => 'صالح حتى', 'value' => $t['validity_until'] ?? null],
                ]
                : [
                    ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'value' => $t['document_number'], 'nowrap' => true],
                    ['en' => 'Payment terms', 'ar' => 'شروط الدفع', 'value' => $t['payment_terms_en'] ?? null, 'value_ar' => $t['payment_terms_ar'] ?? null],
                    ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null],
                ];
        @endphp

        @php($buyerRows = \App\Support\Invoicing\BuyerPrintBlock::rows($t['customer'] ?? null))
        @if ($isQuotation)
            <x-invoices.print-buyer-table
                :rows="$buyerRows"
                :split-left="$splitLeft"
                :split-right="[
                    ['en' => 'Issue date', 'ar' => 'تاريخ الإصدار', 'value' => $t['issue_date']],
                ]"
            />
        @else
            <x-invoices.print-info-grid :rows="\App\Support\Invoicing\InvoicePrintMetaRows::modernIntroGrid($t)" />
            @if (count($buyerRows) > 0)
                <x-invoices.print-buyer-table :rows="$buyerRows" />
            @endif
        @endif
        </div>

        <table class="invoice-a4__table invoice-a4__table--product-lines print-tpl-modern__lines">
            <thead>
                <tr>
                    <th class="col-index" scope="col"><x-invoices.print-th-label en="#" ar="م" align="center" /></th>
                    <th class="col-desc" scope="col"><x-invoices.print-th-label en="Description" ar="الوصف" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Qty" ar="الكمية" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Price" ar="السعر" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Taxable amount" ar="المبلغ الخاضع للضريبة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="VAT amount (+%)" ar="القيمة المضافة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Line amount" ar="المجموع" align="end" /></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td class="col-index tabular-nums">{{ $line['line_no'] }}</td>
                        <td class="col-desc">
                            <div class="invoice-a4__desc-en">{{ $line['name_en'] }}</div>
                            @if ($line['name_ar'])
                                <div class="invoice-a4__desc-ar invoice-print-ar" dir="rtl">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="col-num tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="col-num tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="col-num tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="col-num tabular-nums print-tpl-modern__vat-cell">
                            <span><x-invoices.sar-symbol /> {{ number_format($line['vat_amount'], 2) }}</span>
                            <span class="print-tpl-modern__vat-pct">{{ number_format($line['vat_rate'], 0) }}%</span>
                        </td>
                        <td class="col-num tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <section class="print-tpl-modern__closing">
            @include('print.partials.modern-totals-table', ['tot' => $tot, 't' => $t])
            <div class="invoice-print-words-block print-tpl-modern__words">
                @if (filled($t['amount_words_en'] ?? null))
                    <p class="invoice-print-words-block__en invoice-a4__words">{{ $t['amount_words_en'] }}</p>
                @endif
                @if (filled($t['amount_words_ar'] ?? null))
                    <p class="invoice-print-words-block__ar invoice-a4__words invoice-pdf-ar-block" dir="rtl" lang="ar">{{ $t['amount_words_ar'] }}</p>
                @endif
            </div>
            @if (($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null))
                <div class="print-tpl-modern__qr-wrap">
                    <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :png="$t['qr_png'] ?? null" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
                </div>
            @endif
        </section>

        <footer class="invoice-a4__footer print-tpl-modern__sheet-footer" data-document-number="{{ $t['document_number'] }}">
            <div class="print-tpl-modern__footer-name-en">{{ $t['seller_en'] }}</div>
            <x-invoices.print-page-label :document-number="$t['document_number']" class="print-tpl-modern__footer-page" />
            <div class="print-tpl-modern__footer-name-ar invoice-a4__footer-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
        </footer>
    </div>
</x-layouts.print>