<x-layouts.print>
    @push('head')
        <title>{{ ($t['is_simplified'] ?? false) ? 'Simplified Tax Invoice' : 'Tax Invoice' }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet invoice-print-sheet--cash" data-invoice-print data-auto-print="{{ request()->boolean('autoprint') ? '1' : '0' }}" data-document-number="{{ $t['document_number'] }}">
        <header class="invoice-print-toolbar no-print flex flex-wrap gap-2 p-2">
            <button type="button" class="tap-action-primary min-h-11" onclick="window.print()">{{ __('pos.print') }}</button>
            @if ($receiptUrl ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}">{{ __('invoices.layout_receipt') }}</a>
            @endif
            @if ($a4Url ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $a4Url }}">{{ __('invoices.layout_a4') }}</a>
            @endif
        </header>

        @include('print.partials.not-cleared-watermark')

        @if ($zatcaNotSent ?? false)
            <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
                {{ __('invoices.signing.zatca_not_sent') }}
            </p>
        @endif

        <x-invoices.print-document-title :t="$t" />

        <section class="invoice-cash__seller">
            <strong>{{ $t['seller_en'] }}</strong>
            <div class="invoice-cash__seller-ar">{{ $t['seller_ar'] }}</div>
            @if ($t['address_en'])
                <p class="m-0">{{ $t['address_en'] }}</p>
            @endif
            @if ($t['address_ar'])
                <p class="m-0 invoice-cash__seller-ar">{{ $t['address_ar'] }}</p>
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
            <div class="flex justify-between">
                <span><x-invoices.print-label en="Subtotal (ex VAT)" ar="المجموع (بدون ضريبة)" /></span>
                <span><x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'], 2) }}</span>
            </div>
            @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
                <div class="flex justify-between">
                    <span><x-invoices.print-label en="Discount" ar="الخصم" /></span>
                    <span>- <x-invoices.sar-symbol /> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between">
                <span><x-invoices.print-label en="Total VAT" ar="مجموع الضريبة" /></span>
                <span><x-invoices.sar-symbol /> {{ number_format($tot['total_vat'], 2) }}</span>
            </div>
            <div class="flex justify-between font-bold text-base">
                <span><x-invoices.print-label en="Total (inc VAT)" ar="الإجمالي (شامل الضريبة)" /></span>
                <span><x-invoices.sar-symbol /> {{ number_format($tot['total_inc_vat'], 2) }}</span>
            </div>
            @if (($tot['retention_amount'] ?? 0) > 0)
                <div class="flex justify-between font-semibold">
                    <span><x-invoices.print-label en="Retention" ar="المحجوز" /></span>
                    <span>- <x-invoices.sar-symbol /> {{ number_format($tot['retention_amount'], 2) }}</span>
                </div>
                <div class="flex justify-between font-bold">
                    <span><x-invoices.print-label en="Net due" ar="الصافي المستحق" /></span>
                    <span><x-invoices.sar-symbol /> {{ number_format($t['net_due_inc_vat'], 2) }}</span>
                </div>
            @endif
            <p class="invoice-a4__words mt-2" dir="rtl">{{ $t['amount_words_ar'] }}</p>
            <p class="invoice-a4__words">{{ $t['amount_words_en'] }}</p>
        </section>

        <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />

        <footer class="invoice-cash__footer print-only">
            {{ $t['document_number'] }}
        </footer>
    </div>
</x-layouts.print>