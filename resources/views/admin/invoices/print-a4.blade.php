@php
    /** @var array<string, mixed> $t */
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ ($t['is_simplified'] ?? false) ? 'Simplified Tax Invoice' : 'Tax Invoice' }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js', 'resources/js/invoice-print-preview.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet" data-invoice-print data-document-number="{{ $t['document_number'] }}">
        <header class="invoice-print-toolbar no-print flex flex-wrap gap-2 p-2">
            <button type="button" class="tap-action-primary min-h-11" onclick="window.print()">{{ __('pos.print') }}</button>
            @if ($receiptUrl ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}">{{ __('invoices.layout_receipt') }}</a>
            @endif
            @if ($cashUrl ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $cashUrl }}">{{ __('invoices.layout_cash') }}</a>
            @endif
        </header>

        @include('print.partials.not-cleared-watermark')

        @if ($zatcaNotSent ?? false)
            <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
                {{ __('invoices.signing.zatca_not_sent') }}
            </p>
        @endif

        @include('print.partials.seller-header-with-ids', ['t' => $t])
        <x-invoices.print-document-title :t="$t" />

        <x-invoices.print-info-grid :rows="\App\Support\Invoicing\InvoicePrintMetaRows::legacyA4IntroGrid($t)" />

        @php($a4BuyerRows = \App\Support\Invoicing\BuyerPrintBlock::rows($t['customer'] ?? null))
        @if (count($a4BuyerRows) > 0)
            <x-invoices.print-buyer-table :rows="$a4BuyerRows" />
        @endif

        <table class="invoice-a4__table invoice-a4__table--product-lines">
            <thead>
                <tr>
                    <th class="col-index" scope="col"><x-invoices.print-th-label en="#" ar="م" align="center" /></th>
                    <th class="col-desc" scope="col"><x-invoices.print-th-label en="Description" ar="الوصف" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Qty" ar="الكمية" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Unit price" ar="سعر الوحدة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Taxable" ar="الخاضع" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="VAT" ar="الضريبة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Total" ar="الإجمالي" align="end" /></th>
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
                        <td class="col-num tabular-nums">{{ number_format($line['vat_rate'], 0) }}% · <x-invoices.sar-symbol /> {{ number_format($line['vat_amount'], 2) }}</td>
                        <td class="col-num tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <table class="invoice-a4__totals">
            <tr>
                <td><x-invoices.print-label en="Subtotal (ex VAT)" ar="المجموع (بدون ضريبة)" /></td>
                <td><x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'], 2) }}</td>
            </tr>
            @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
                <tr>
                    <td><x-invoices.print-label en="Discount" ar="الخصم" /></td>
                    <td>- <x-invoices.sar-symbol /> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}</td>
                </tr>
            @endif
            <tr>
                <td><x-invoices.print-label en="Total VAT" ar="مجموع الضريبة" /></td>
                <td><x-invoices.sar-symbol /> {{ number_format($tot['total_vat'], 2) }}</td>
            </tr>
            <tr>
                <td><x-invoices.print-label en="Total (inc VAT)" ar="الإجمالي (شامل الضريبة)" /></td>
                <td><x-invoices.sar-symbol /> {{ number_format($tot['total_inc_vat'], 2) }}</td>
            </tr>
            @if (($tot['retention_amount'] ?? 0) > 0)
                <tr>
                    <td><x-invoices.print-label en="Retention" ar="المحجوز" /></td>
                    <td>- <x-invoices.sar-symbol /> {{ number_format($tot['retention_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td><strong><x-invoices.print-label en="Net due" ar="الصافي المستحق" /></strong></td>
                    <td><strong><x-invoices.sar-symbol /> {{ number_format($t['net_due_inc_vat'], 2) }}</strong></td>
                </tr>
            @endif
        </table>

        <div class="invoice-print-words-block">
            <x-invoices.print-amount-words-en
                :text="$t['amount_words_en'] ?? null"
                :is-pdf="false"
                class="invoice-print-words-block__en"
            />
            <x-invoices.print-amount-words-ar
                :text="$t['amount_words_ar'] ?? null"
                class="invoice-print-words-block__ar invoice-print-ar"
            />
        </div>

        <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />

        <footer class="invoice-a4__footer print-only" data-seller-en="{{ $t['seller_en'] }}" data-seller-ar="{{ $t['seller_ar'] }}" data-document-number="{{ $t['document_number'] }}">
            <div>{{ $t['seller_en'] }}</div>
            <div class="invoice-a4__footer-ar">{{ $t['seller_ar'] }}</div>
            <x-invoices.print-page-label :document-number="$t['document_number']" />
        </footer>
    </div>
</x-layouts.print>