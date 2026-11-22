<x-layouts.print>
    @push('head')
        <title>{{ trans('quotations.print_title', [], 'en') }} {{ $t['quotation_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet invoice-print-sheet--cash" data-invoice-print data-document-number="{{ $t['quotation_number'] }}">
        <header class="invoice-print-toolbar no-print flex flex-wrap gap-2 p-2">
            <button type="button" class="tap-action-primary min-h-11" onclick="window.print()">{{ trans('pos.print', [], 'en') }}</button>
        </header>

        <h1 class="invoice-a4__title">
            <span class="invoice-a4__title-en">{{ trans('quotations.print_title', [], 'en') }}</span>
            <span class="invoice-a4__title-ar invoice-print-ar block" dir="rtl">{{ trans('quotations.print_title', [], 'ar') }}</span>
        </h1>

        <section class="invoice-cash__seller">
            <strong>{{ $t['seller_en'] }}</strong>
            <div class="invoice-cash__seller-ar invoice-print-ar" dir="rtl">{{ $t['seller_ar'] }}</div>
            @if ($t['address_en'])
                <p class="m-0">{{ $t['address_en'] }}</p>
            @endif
            <x-invoices.print-seller-header-ids :t="$t" side="en" />
            <x-invoices.print-seller-header-ids :t="$t" side="ar" />
        </section>

        <x-invoices.print-meta-grid :rows="[
            ['en' => 'Quotation No.', 'ar' => 'رقم العرض', 'value' => $t['quotation_number'], 'nowrap' => true],
            ['en' => 'Date', 'ar' => 'التاريخ', 'value' => $t['issue_date']],
            ['en' => 'Valid until', 'ar' => 'صالح حتى', 'value' => $t['validity_until']],
        ]" />

        @if ($t['customer'])
            <div class="invoice-cash__customer">
                @if (! empty($t['customer']['name']))
                    <div class="invoice-cash__customer-name">{{ $t['customer']['name'] }}</div>
                @endif
                @if (! empty($t['customer']['phone']))
                    <x-invoices.print-labeled-row en="Mobile" ar="الجوال" :value="$t['customer']['phone']" :nowrap="true" />
                @endif
            </div>
        @endif

        <table class="invoice-a4__table invoice-a4__table--product-lines invoice-cash__lines">
            <thead>
                <tr>
                    <th class="col-desc" scope="col"><x-invoices.print-th-label en="Description" ar="البيان" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Qty" ar="الكمية" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Unit (ex VAT) (SAR)" ar="السعر (بدون ضريبة)" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="VAT %" ar="الضريبة %" align="end" /></th>
                    <th class="col-num" scope="col"><x-invoices.print-th-label en="Line total (SAR)" ar="الإجمالي" align="end" /></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td class="col-desc">
                            {{ $line['name'] }}
                            @if (! empty($line['name_ar']))
                                <div class="invoice-a4__desc-ar invoice-print-ar" dir="rtl">{{ $line['name_ar'] }}</div>
                            @endif
                        </td>
                        <td class="col-num tabular-nums">{{ number_format($line['qty'], 3) }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['unit_price'], 2) }}</td>
                        <td class="col-num tabular-nums">{{ number_format($line['vat_rate'], 0) }}%</td>
                        <td class="col-num tabular-nums">{{ number_format($line['line_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="invoice-cash__amount-block">
            <table class="invoice-print-totals invoice-cash__totals" role="presentation">
                <x-invoices.print-totals-row en="Subtotal (ex VAT)" ar="المجموع (بدون ضريبة)">
                    {{ number_format($t['totals']['subtotal_ex_vat'], 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="VAT" ar="ضريبة القيمة المضافة">
                    {{ number_format($t['totals']['total_vat'], 2) }}
                </x-invoices.print-totals-row>
                <x-invoices.print-totals-row en="Total (inc VAT)" ar="الإجمالي (شامل الضريبة)" :strong="true">
                    <x-invoices.sar-symbol /> {{ number_format($t['totals']['total_inc_vat'], 2) }}
                </x-invoices.print-totals-row>
            </table>
        </section>
    </div>
</x-layouts.print>