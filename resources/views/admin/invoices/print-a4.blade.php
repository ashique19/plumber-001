@php
    /** @var array<string, mixed> $t */
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ ($t['is_simplified'] ?? false) ? 'Simplified Tax Invoice' : 'Tax Invoice' }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
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

        @if ($zatcaNotSent ?? false)
            <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
                {{ __('invoices.signing.zatca_not_sent') }}
            </p>
        @endif

        <section class="invoice-a4__header">
            <div class="invoice-a4__seller-side">
                <strong>{{ $t['seller_en'] }}</strong>
                @if ($t['address_en'])
                    <p class="m-0">{{ $t['address_en'] }}</p>
                @endif
                @if ($t['trn'])
                    <p class="m-0"><x-invoices.print-label en="VAT No." ar="الرقم الضريبي" />: {{ $t['trn'] }}</p>
                @endif
                @if ($t['cr_number'])
                    <p class="m-0"><x-invoices.print-label en="CR No." ar="السجل التجاري" />: {{ $t['cr_number'] }}</p>
                @endif
            </div>
            <div>
                @if ($t['logo_url'])
                    <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo">
                @endif
            </div>
            <div class="invoice-a4__seller-side invoice-a4__seller-side--ar">
                <strong>{{ $t['seller_ar'] }}</strong>
                @if ($t['address_ar'])
                    <p class="m-0">{{ $t['address_ar'] }}</p>
                @endif
                @if ($t['mobile'])
                    <p class="m-0"><x-invoices.print-label en="Mobile" ar="الجوال" />: {{ $t['mobile'] }}@if($t['mobile_secondary']) / {{ $t['mobile_secondary'] }}@endif</p>
                @endif
            </div>
        </section>

        <x-invoices.print-document-title :t="$t" />

        <x-invoices.print-buyer-table
            :rows="\App\Support\Invoicing\BuyerPrintBlock::rows($t['customer'] ?? null)"
            :split-left="[
                ['en' => 'Invoice number', 'ar' => 'رقم الفاتورة', 'value' => $t['document_number']],
                ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => $t['due_date'] ?? null],
            ]"
            :split-right="[
                ['en' => 'Date', 'ar' => 'التاريخ', 'value' => $t['issue_date']],
                ['en' => 'Supply date', 'ar' => 'تاريخ التوريد', 'value' => $t['supply_date'] ?? null],
            ]"
        />

        <table class="invoice-a4__table">
            <thead>
                <tr>
                    <th>#<span class="ar">م</span></th>
                    <th>Description<span class="ar">الوصف</span></th>
                    <th>Qty<span class="ar">الكمية</span></th>
                    <th>Unit price<span class="ar">سعر الوحدة</span></th>
                    <th>Taxable<span class="ar">الخاضع</span></th>
                    <th>VAT<span class="ar">الضريبة</span></th>
                    <th>Total<span class="ar">الإجمالي</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td>{{ $line['line_no'] }}</td>
                        <td>
                            <div class="invoice-a4__desc-en">{{ $line['name_en'] }}</div>
                            @if ($line['name_ar'])
                                <div class="invoice-a4__desc-ar">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ $line['quantity_display'] }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['vat_rate'], 0) }}% · <x-invoices.sar-symbol /> {{ number_format($line['vat_amount'], 2) }}</td>
                        <td class="tabular-nums"><x-invoices.sar-symbol /> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
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

        <p class="invoice-a4__words" dir="rtl">{{ $t['amount_words_ar'] }}</p>
        <p class="invoice-a4__words">{{ $t['amount_words_en'] }}</p>

        <x-invoices.print-qr :tlv="$t['qr_tlv_base64']" :fallback="(bool) ($t['qr_phase1_fallback'] ?? false)" />

        <footer class="invoice-a4__footer print-only" data-seller-en="{{ $t['seller_en'] }}" data-seller-ar="{{ $t['seller_ar'] }}">
            <div>{{ $t['seller_en'] }}</div>
            <div class="invoice-a4__footer-ar">{{ $t['seller_ar'] }}</div>
        </footer>
    </div>
</x-layouts.print>