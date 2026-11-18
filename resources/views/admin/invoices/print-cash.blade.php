<x-layouts.print>
    @push('head')
        <title>{{ ($t['is_simplified'] ?? false) ? 'Simplified Tax Invoice' : 'Tax Invoice' }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet invoice-print-sheet--cash" data-invoice-print data-auto-print="{{ request()->boolean('autoprint') ? '1' : '0' }}" data-document-number="{{ $t['document_number'] }}">
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

        <x-invoices.print-document-title :t="$t" />

        <section class="invoice-cash__seller">
            <strong>{{ $t['seller_en'] }}</strong>
            <div class="invoice-cash__seller-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            @if ($t['address_en'])
                <p class="m-0">{{ $t['address_en'] }}</p>
            @endif
            <x-invoices.print-seller-header-ids :t="$t" side="en" />
            @php
                $na = (array) ($t['national_address'] ?? []);
                $structuredAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
            @endphp
            @if ($structuredAddressLines !== [])
                <div @class(['invoice-national-address-ar-lines', 'invoice-national-address-ar--pdf' => $isPdf ?? false]) lang="ar">
                    @foreach ($structuredAddressLines as $line)
                        <div @class([
                            'invoice-national-address-ar__line',
                            'invoice-cash__seller-ar',
                            'invoice-print-ar',
                            'invoice-pdf-ar-address-line' => $isPdf ?? false,
                        ]) dir="rtl" style="text-align:right">{!! $line !!}</div>
                    @endforeach
                </div>
            @elseif ($isPdf ?? false)
                @if ($t['address_ar'])
                    <p class="m-0 invoice-cash__seller-ar invoice-print-ar invoice-national-address-ar--pdf" dir="rtl" lang="ar">{{ $t['address_ar'] }}</p>
                @endif
            @elseif ($t['address_ar_html'] ?? null)
                <p class="m-0 invoice-cash__seller-ar">{!! $t['address_ar_html'] !!}</p>
            @elseif ($t['address_ar'])
                <p class="m-0 invoice-cash__seller-ar invoice-print-ar" dir="rtl">{{ $t['address_ar'] }}</p>
            @endif
            <x-invoices.print-seller-header-ids :t="$t" side="ar" />
        </section>

        <x-invoices.print-info-grid :rows="\App\Support\Invoicing\InvoicePrintMetaRows::cashIntroGrid($t)" />

        @if (\App\Support\Invoicing\InvoicePrintCustomerBlock::hasVisibleContent($t['customer'] ?? null))
            <div class="invoice-cash__customer">
                @if (! empty($t['customer']['name']))
                    <div class="invoice-cash__customer-name">{{ $t['customer']['name'] }}</div>
                @endif
                @if (! empty($t['customer']['phone']))
                    <x-invoices.print-labeled-row en="Mobile" ar="الجوال" :value="$t['customer']['phone']" :nowrap="true" />
                @endif
                @if (! empty($t['customer']['vat_number']))
                    <x-invoices.print-labeled-row en="VAT No." ar="الرقم الضريبي" :value="$t['customer']['vat_number']" :nowrap="true" />
                @endif
                @if (! empty($t['customer']['cr_number']))
                    <x-invoices.print-labeled-row en="CR No." ar="السجل التجاري" :value="$t['customer']['cr_number']" :nowrap="true" />
                @endif
                @if (! empty($t['customer']['address']))
                    <div class="invoice-cash__customer-address">{{ $t['customer']['address'] }}</div>
                @endif
            </div>
        @endif

        <table class="invoice-a4__table invoice-a4__table--product-lines">
            <thead>
                <tr>
                    <th class="col-index" scope="col"><x-invoices.print-th-label en="#" ar="م" align="center" /></th>
                    <th class="col-desc" scope="col"><x-invoices.print-th-label en="Description" ar="الوصف" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Qty" ar="الكمية" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Unit price" ar="سعر الوحدة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="VAT %" ar="ضريبة" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Line total" ar="إجمالي البند" align="end" /></th>
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
                        <td class="col-num tabular-nums">{{ number_format($line['vat_rate'], 0) }}%</td>
                        <td class="col-num tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <section class="invoice-cash__amount-block">
            <table class="invoice-print-totals invoice-cash__totals" role="presentation">
                <x-invoices.print-totals-row en="Subtotal (ex VAT)" ar="المجموع (بدون ضريبة)">
                    <x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'], 2) }}
                </x-invoices.print-totals-row>
                @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
                    <x-invoices.print-totals-row en="Discount" ar="الخصم">
                        - <x-invoices.sar-symbol /> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}
                    </x-invoices.print-totals-row>
                @endif
                <x-invoices.print-totals-row en="Total VAT" ar="مجموع الضريبة">
                    <x-invoices.sar-symbol /> {{ number_format($tot['total_vat'], 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="Total (inc VAT)" ar="الإجمالي (شامل الضريبة)" :strong="true">
                    <x-invoices.sar-symbol /> {{ number_format($tot['total_inc_vat'], 2) }}
                </x-invoices.print-totals-row>
                @if (($tot['retention_amount'] ?? 0) > 0)
                    <x-invoices.print-totals-row en="Retention" ar="المحجوز">
                        - <x-invoices.sar-symbol /> {{ number_format($tot['retention_amount'], 2) }}
                    </x-invoices.print-totals-row>
                    <x-invoices.print-totals-row en="Net due" ar="الصافي المستحق" :strong="true">
                        <x-invoices.sar-symbol /> {{ number_format($t['net_due_inc_vat'], 2) }}
                    </x-invoices.print-totals-row>
                @endif
            </table>
            <div class="invoice-print-words-block invoice-cash__words">
                @if (filled($t['amount_words_en'] ?? null))
                    <p class="invoice-print-words-block__en invoice-a4__words">{{ $t['amount_words_en'] }}</p>
                @endif
                @if (filled($t['amount_words_ar'] ?? null))
                    <p class="invoice-print-words-block__ar invoice-a4__words invoice-print-ar" dir="rtl">{{ $t['amount_words_ar'] }}</p>
                @endif
            </div>
        </section>

        @if (($t['show_qr'] ?? false) && filled($t['qr_tlv_base64'] ?? null))
            <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :png="$t['qr_png'] ?? null" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
        @endif

        <footer class="invoice-cash__footer print-only">
            {{ $t['document_number'] }}
        </footer>
    </div>
</x-layouts.print>