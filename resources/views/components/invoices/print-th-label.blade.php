@props([
    'en',
    'ar',
    'align' => 'start',
])

<span {{ $attributes->class([
    'invoice-print-th-label',
    'invoice-print-th-label--'.$align,
]) }}>
    <span class="invoice-print-th-label__en">{{ $en }}</span>
    <span class="invoice-print-th-label__ar ar invoice-print-ar" dir="rtl">{{ $ar }}</span>
</span>