@php
    /** @var array<string, mixed> $tot */
@endphp
<table class="print-tpl-modern__totals invoice-print-totals" role="presentation">
    <x-invoices.print-totals-row en="Subtotal" ar="المجموع الفرعي">
        <x-invoices.sar-symbol /> {{ number_format($tot['subtotal_ex_vat'], 2) }}
    </x-invoices.print-totals-row>
    @if (($tot['invoice_discount_ex_vat'] ?? 0) > 0)
        <x-invoices.print-totals-row en="Discount" ar="الخصم">
            - <x-invoices.sar-symbol /> {{ number_format($tot['invoice_discount_ex_vat'], 2) }}
        </x-invoices.print-totals-row>
    @endif
    <x-invoices.print-totals-row en="Total VAT" ar="إجمالي ضريبة القيمة المضافة">
        <x-invoices.sar-symbol /> {{ number_format($tot['total_vat'], 2) }}
    </x-invoices.print-totals-row>
    <x-invoices.print-totals-row
        en="Total"
        ar="المجموع شامل القيمة المضافة"
        :strong="true"
        row-class="print-tpl-modern__totals-total"
    >
        <x-invoices.sar-symbol /> {{ number_format($tot['total_inc_vat'], 2) }}
    </x-invoices.print-totals-row>
    @if (($tot['retention_amount'] ?? 0) > 0)
        <x-invoices.print-totals-row en="Retention" ar="حجز حسن التنفيذ">
            - <x-invoices.sar-symbol /> {{ number_format($tot['retention_amount'], 2) }}
        </x-invoices.print-totals-row>
    @endif
    @if (\App\Support\Invoicing\InvoicePrintTotalsBlock::shouldShowNetDue($t, $tot))
        <x-invoices.print-totals-row
            en="Net due"
            ar="المبلغ المستحق"
            :strong="true"
            row-class="print-tpl-modern__totals-net"
        >
            <x-invoices.sar-symbol /> {{ number_format($t['net_due_inc_vat'], 2) }}
        </x-invoices.print-totals-row>
    @endif
</table>