@php
    /** @var array<string, mixed> $t */
@endphp
<x-layouts.print>
    @push('head')
        <title>{{ __('invoices.print_a4') }} {{ $t['document_number'] }}</title>
        @vite(['resources/css/invoice-print.css', 'resources/js/receipt.js'])
    @endpush

    <div class="invoice-print-root invoice-print-sheet" data-invoice-print>
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
                @if ($t['short_national_address'])
                    <p class="m-0 text-[var(--inv-muted)]">{{ $t['short_national_address'] }}</p>
                @endif
                @if ($t['trn'])
                    <p class="m-0">{{ __('pos.receipt_trn') }}: {{ $t['trn'] }}</p>
                @endif
                @if ($t['unified_number'])
                    <p class="m-0">{{ __('invoices.unified_number') }}: {{ $t['unified_number'] }}</p>
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
                @if ($t['cr_number'])
                    <p class="m-0">{{ __('pos.receipt_cr_no') }}: {{ $t['cr_number'] }}</p>
                @endif
                @if ($t['mobile'])
                    <p class="m-0">{{ __('invoices.mobile') }}: {{ $t['mobile'] }}@if($t['mobile_secondary']) / {{ $t['mobile_secondary'] }}@endif</p>
                @endif
            </div>
        </section>

        <h1 class="invoice-a4__title">
            Tax Invoice
            <span class="invoice-a4__title-ar block">فاتورة ضريبية</span>
        </h1>

        <div class="invoice-a4__customer">
            @foreach ([
                ['en' => 'Customer', 'ar' => 'العميل', 'key' => 'name', 'val' => $t['customer']['name'] ?? null],
                ['en' => 'Address', 'ar' => 'العنوان', 'key' => 'address', 'val' => $t['customer']['address'] ?? null],
                ['en' => 'Email', 'ar' => 'البريد', 'key' => 'email', 'val' => $t['customer']['email'] ?? null],
                ['en' => 'Phone', 'ar' => 'الجوال', 'key' => 'phone', 'val' => $t['customer']['phone'] ?? null],
                ['en' => 'VAT', 'ar' => 'الرقم الضريبي', 'key' => 'vat', 'val' => $t['customer']['vat_number'] ?? null],
                ['en' => 'Invoice No.', 'ar' => 'رقم الفاتورة', 'key' => 'inv', 'val' => $t['document_number']],
                ['en' => 'Date', 'ar' => 'التاريخ', 'key' => 'date', 'val' => $t['issue_date']],
                ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'key' => 'due', 'val' => $t['due_date'] ?? null],
            ] as $row)
                @if (filled($row['val'] ?? null))
                    <div>
                        <span class="invoice-a4__customer-label">
                            {{ $row['en'] }}
                            <span class="ar">{{ $row['ar'] }}</span>
                        </span>
                        <div>{{ $row['val'] }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        <table class="invoice-a4__table">
            <thead>
                <tr>
                    <th>#<span class="ar">م</span></th>
                    <th>Description<span class="ar">الوصف</span></th>
                    <th>Qty<span class="ar">الكمية</span></th>
                    <th>Price<span class="ar">السعر</span></th>
                    <th>Taxable<span class="ar">الخاضع</span></th>
                    <th>VAT<span class="ar">الضريبة</span></th>
                    <th>Line<span class="ar">الإجمالي</span></th>
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
                        <td class="tabular-nums"><span class="sar-symbol"></span> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                        <td class="tabular-nums"><span class="sar-symbol"></span> {{ number_format($line['taxable_ex_vat'], 2) }}</td>
                        <td class="tabular-nums">{{ number_format($line['vat_rate'], 0) }}% · <span class="sar-symbol"></span> {{ number_format($line['vat_amount'], 2) }}</td>
                        <td class="tabular-nums"><span class="sar-symbol"></span> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tot = $t['totals'])
        <table class="invoice-a4__totals">
            <tr><td>{{ __('invoices.subtotal_ex_vat') }}</td><td><span class="sar-symbol"></span> {{ number_format($tot['subtotal_ex_vat'], 2) }}</td></tr>
            @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
                <tr><td>{{ __('invoices.invoice_discount_ex_vat') }}</td><td>- <span class="sar-symbol"></span> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}</td></tr>
            @endif
            <tr><td>{{ __('invoices.total_vat') }}</td><td><span class="sar-symbol"></span> {{ number_format($tot['total_vat'], 2) }}</td></tr>
            <tr><td>{{ __('invoices.total_inc_vat') }}</td><td><span class="sar-symbol"></span> {{ number_format($tot['total_inc_vat'], 2) }}</td></tr>
            @if (($tot['retention_amount'] ?? 0) > 0)
                <tr><td>{{ __('invoices.retention') }}</td><td>- <span class="sar-symbol"></span> {{ number_format($tot['retention_amount'], 2) }}</td></tr>
                <tr><td><strong>{{ __('invoices.net_due') }}</strong></td><td><strong><span class="sar-symbol"></span> {{ number_format($t['net_due_inc_vat'], 2) }}</strong></td></tr>
            @endif
        </table>

        <p class="invoice-a4__words" dir="rtl">{{ $t['amount_words_ar'] }}</p>
        <p class="invoice-a4__words">{{ $t['amount_words_en'] }}</p>

        @if ($t['qr_tlv_base64'])
            <div class="invoice-a4__qr">
                <canvas id="receipt-qr" data-tlv="{{ $t['qr_tlv_base64'] }}" width="180" height="180"></canvas>
                <p class="invoice-a4__qr-note">{{ __('pos.scan_qr_note') }}</p>
            </div>
        @endif

        <footer class="invoice-a4__footer print-only">
            <div>{{ $t['seller_en'] }}</div>
            <div class="invoice-a4__footer-ar">{{ $t['seller_ar'] }}</div>
            <div>{{ $t['document_number'] }}</div>
        </footer>
    </div>
</x-layouts.print>