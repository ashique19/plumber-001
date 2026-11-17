@props(['amount', 'class' => ''])

<span {{ $attributes->class(['payments-dues-amount inline-flex items-center justify-end gap-1 tabular-nums font-medium', $class]) }}>
    <span>{{ number_format((float) $amount, 2) }}</span>
    <x-invoices.sar-symbol class="payments-dues-amount__sar" />
</span>