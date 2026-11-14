<x-layouts.print>
    @push('head')
        <title>{{ __('invoices.print_cash') }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet" data-invoice-print>
        <header class="invoice-print-toolbar no-print flex flex-wrap gap-2 p-2">
            <button type="button" class="tap-action-primary min-h-11" onclick="window.print()">{{ __('pos.print') }}</button>
            @if ($receiptUrl ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $receiptUrl }}">{{ __('invoices.layout_receipt') }}</a>
            @endif
            @if ($a4Url ?? null)
                <a class="tap-action-muted min-h-11" href="{{ $a4Url }}">{{ __('invoices.layout_a4') }}</a>
            @endif
        </header>

        @if ($zatcaNotSent ?? false)
            <p class="invoice-zatca-notice no-print m-2 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">
                {{ __('invoices.signing.zatca_not_sent') }}
            </p>
        @endif

        <section class="invoice-cash__seller">
            <strong>{{ $t['seller_en'] }}</strong>
            <div class="invoice-cash__seller-ar">{{ $t['seller_ar'] }}</div>
            @if ($t['cr_number'])
                <p class="m-0">{{ __('pos.receipt_cr_no') }}: {{ $t['cr_number'] }}</p>
            @endif
            @if ($t['trn'])
                <p class="m-0">{{ __('pos.receipt_trn') }}: {{ $t['trn'] }}</p>
            @endif
            @if ($t['mobile'])
                <p class="m-0">{{ __('invoices.mobile') }}: {{ $t['mobile'] }}@if($t['mobile_secondary']) · {{ $t['mobile_secondary'] }}@endif</p>
            @endif
        </section>

        <div class="invoice-cash__meta">
            <div>{{ __('invoices.type_filter') }}: {{ $t['document_type_label_en'] }} / {{ $t['document_type_label_ar'] }}</div>
            <div>{{ __('invoices.ref') }}: {{ $t['document_number'] }}</div>
            <div>{{ __('invoices.issue_date') }}: {{ $t['issue_date'] }}</div>
            <div>{{ $t['payment_display'] }}</div>
        </div>

        @if ($t['customer'])
            <div class="invoice-cash__customer">
                @if (! empty($t['customer']['name']))<div>{{ $t['customer']['name'] }}</div>@endif
                @if (! empty($t['customer']['phone']))<div>{{ __('invoices.mobile') }}: {{ $t['customer']['phone'] }}</div>@endif
                @if (! empty($t['customer']['vat_number']))<div>{{ __('pos.receipt_trn') }}: {{ $t['customer']['vat_number'] }}</div>@endif
                @if (! empty($t['customer']['address']))<div>{{ $t['customer']['address'] }}</div>@endif
            </div>
        @endif

        <table class="invoice-a4__table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('invoices.line_name') }}</th>
                    <th>{{ __('invoices.line_qty') }}</th>
                    <th>{{ __('invoices.line_amount') }}</th>
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
                        <td>{{ $line['quantity_display'] }}</td>
                        <td><span class="sar-symbol"></span> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <section class="invoice-cash__amount-block">
            <div class="flex justify-between"><span>{{ __('invoices.cash_amount') }}</span><span><span class="sar-symbol"></span> {{ number_format($tot['subtotal_ex_vat'], 2) }}</span></div>
            @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
                <div class="flex justify-between"><span>{{ __('invoices.invoice_discount_ex_vat') }}</span><span>- <span class="sar-symbol"></span> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}</span></div>
            @endif
            <div class="flex justify-between font-semibold"><span>{{ __('invoices.cash_net_amount') }}</span><span><span class="sar-symbol"></span> {{ number_format($tot['subtotal_ex_vat'] - ($tot['invoice_discount_ex_vat'] ?? 0), 2) }}</span></div>
            <div class="flex justify-between"><span>{{ __('invoices.total_vat') }}</span><span><span class="sar-symbol"></span> {{ number_format($tot['total_vat'], 2) }}</span></div>
            <div class="flex justify-between font-bold text-base"><span>{{ __('invoices.total_inc_vat') }}</span><span><span class="sar-symbol"></span> {{ number_format($tot['total_inc_vat'], 2) }}</span></div>
            <p class="label-ar mt-2">{{ $t['amount_words_ar'] }}</p>
        </section>

        @if ($t['qr_tlv_base64'])
            <div class="invoice-a4__qr">
                <canvas id="receipt-qr" data-tlv="{{ $t['qr_tlv_base64'] }}" width="160" height="160"></canvas>
                <p class="invoice-a4__qr-note">{{ __('pos.scan_qr_note') }}</p>
            </div>
        @endif
    </div>
</x-layouts.print>