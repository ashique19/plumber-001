@props([
    'en',
    'ar',
    'value' => null,
    'nowrap' => false,
])

<div {{ $attributes->class(['invoice-print-labeled-row']) }}>
    <span class="invoice-print-labeled-row__en">{{ $en }}</span>
    <span @class([
        'invoice-print-labeled-row__value',
        'invoice-print-labeled-row__value--nowrap' => $nowrap,
    ]) @if ($nowrap) dir="ltr" @endif>
        @if (filled($value))
            {{ $value }}
        @else
            {{ $slot }}
        @endif
    </span>
    <span class="invoice-print-labeled-row__ar invoice-print-ar" dir="rtl">{{ $ar }}</span>
</div>