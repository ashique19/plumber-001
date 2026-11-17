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
        ])

        <x-invoices.print-document-title :t="$t" />

        <section class="invoice-cash__seller">
            <strong>{{ $t['seller_en'] }}</strong>
            <div class="invoice-cash__seller-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            @if ($t['address_en'])
                <p class="m-0">{{ $t['address_en'] }}</p>
            @endif
            @if ($t['address_ar'])
                <p class="m-0 invoice-cash__seller-ar invoice-print-ar" dir="rtl">{{ $t['address_ar'] }}</p>
            @endif
            @if ($t['trn'])
                <p class="m-0"><x-invoices.print-label en="VAT No." ar="الرقم الضريبي" />: {{ $t['trn'] }}</p>
            @endif
            @if ($t['cr_number'])
                <p class="m-0"><x-invoices.print-label en="CR No." ar="السجل التجاري" />: {{ $t['cr_number'] }}</p>
            @endif
            @if ($t['mobile'])
                <p class="m-0"><x-invoices.print-label en="Mobile" ar="الجوال" />: {{ $t['mobile'] }}@if($t['mobile_secondary']) · {{ $t['mobile_secondary'] }}@endif</p>
            @endif
        </section>

        <div class="invoice-cash__meta">
            <div><x-invoices.print-label en="Invoice No." ar="رقم الفاتورة" />: {{ $t['document_number'] }}</div>
            <div><x-invoices.print-label en="Issue date" ar="تاريخ الإصدار" />: {{ $t['issue_date'] }}</div>
            <div><x-invoices.print-label en="Supply date" ar="تاريخ التوريد" />: {{ $t['supply_date'] ?? $t['issue_date'] }}</div>
            <div><x-invoices.print-label en="Payment" ar="الدفع" />: {{ $t['payment_display_en'] }} / {{ $t['payment_display_ar'] }}</div>
        </div>

        @if ($t['customer'])
            <div class="invoice-cash__customer">
                @if (! empty($t['customer']['name']))<div>{{ $t['customer']['name'] }}</div>@endif
                @if (! empty($t['customer']['phone']))
                    <div><x-invoices.print-label en="Mobile" ar="الجوال" />: {{ $t['customer']['phone'] }}</div>
                @endif
                @if (! empty($t['customer']['vat_number']))
                    <div><x-invoices.print-label en="VAT No." ar="الرقم الضريبي" />: {{ $t['customer']['vat_number'] }}</div>
                @endif
                @if (! empty($t['customer']['cr_number']))
                    <div><x-invoices.print-label en="CR No." ar="السجل التجاري" />: {{ $t['customer']['cr_number'] }}</div>
                @endif
                @if (! empty($t['customer']['address']))<div>{{ $t['customer']['address'] }}</div>@endif
            </div>
        @endif

        <table class="invoice-a4__table">
            <thead>
                <tr>
                    <th>#<span class="ar">م</span></th>
                    <th>Description<span class="ar">الوصف</span></th>
                    <th>Qty<span class="ar">الكمية</span></th>
                    <th>Unit price<span class="ar">سعر الوحدة</span></th>
                    <th>VAT %<span class="ar">ضريبة</span></th>
                    <th>Line total<span class="ar">إجمالي البند</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td>{{ $line['line_no'] }}</td>
                        <td>
                            {{ $line['name_en'] }}
                            @if ($line['name_ar'])
                                <div class="invoice-a4__desc-ar">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['vat_rate'], 0) }}%</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
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
            <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />
        @endif

        <footer class="invoice-cash__footer print-only">
            {{ $t['document_number'] }}
        </footer>
    </div>
</x-layouts.print>