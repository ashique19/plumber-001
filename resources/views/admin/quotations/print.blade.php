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
            <div class="invoice-cash__seller-ar">{{ $t['seller_ar'] }}</div>
            @if ($t['address_en'])
                <p class="m-0">{{ $t['address_en'] }}</p>
            @endif
            @if ($t['trn'])
                <p class="m-0"><x-invoices.print-label en="VAT No." ar="الرقم الضريبي" />: {{ $t['trn'] }}</p>
            @endif
        </section>

        <div class="invoice-cash__meta">
            <div><x-invoices.print-label en="Quotation No." ar="رقم العرض" />: {{ $t['quotation_number'] }}</div>
            <div><x-invoices.print-label en="Date" ar="التاريخ" />: {{ $t['issue_date'] }}</div>
            <div><x-invoices.print-label en="Valid until" ar="صالح حتى" />: {{ $t['validity_until'] }}</div>
        </div>

        @if ($t['customer'])
            <div class="invoice-cash__customer">
                @if (! empty($t['customer']['name']))<div>{{ $t['customer']['name'] }}</div>@endif
                @if (! empty($t['customer']['phone']))
                    <div><x-invoices.print-label en="Mobile" ar="الجوال" />: {{ $t['customer']['phone'] }}</div>
                @endif
            </div>
        @endif

        <table class="invoice-cash__lines w-full text-sm">
            <thead>
                <tr>
                    <th><x-invoices.print-label en="Description" ar="البيان" /></th>
                    <th><x-invoices.print-label en="Qty" ar="الكمية" /></th>
                    <th><x-invoices.print-label en="Unit (ex VAT)" ar="السعر (بدون ضريبة)" /></th>
                    <th><x-invoices.print-label en="VAT %" ar="الضريبة %" /></th>
                    <th><x-invoices.print-label en="Line total" ar="الإجمالي" /></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['lines'] as $line)
                    <tr>
                        <td>
                            {{ $line['name'] }}
                            @if (! empty($line['name_ar']))
                                <span class="block text-xs" dir="rtl">{{ $line['name_ar'] }}</span>
                            @endif
                        </td>
                        <td class="tabular-nums">{{ number_format($line['qty'], 3) }}</td>
                        <td class="tabular-nums">{{ number_format($line['unit_price'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['vat_rate'], 0) }}</td>
                        <td class="tabular-nums">{{ number_format($line['line_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="invoice-cash__totals text-sm">
            <h2 class="sr-only"><x-invoices.print-label en="Totals" ar="الإجماليات" /></h2>
            <div><x-invoices.print-label en="Subtotal (ex VAT)" ar="المجموع (بدون ضريبة)" />: {{ number_format($t['totals']['subtotal_ex_vat'], 2) }}</div>
            <div><x-invoices.print-label en="VAT" ar="ضريبة القيمة المضافة" />: {{ number_format($t['totals']['total_vat'], 2) }}</div>
            <strong><x-invoices.print-label en="Total (inc VAT)" ar="الإجمالي (شامل الضريبة)" />: {{ number_format($t['totals']['total_inc_vat'], 2) }}</strong>
        </div>
    </div>
</x-layouts.print>