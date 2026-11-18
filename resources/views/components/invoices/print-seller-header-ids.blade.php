@props([
    't',
    'side' => 'en',
])

@php
    $lines = \App\Support\Invoicing\SellerHeaderIds::linesForSide($t, $side);
@endphp

@if ($lines !== [])
    <div @class([
        'invoice-print-seller-header-ids',
        'invoice-print-seller-header-ids--ar' => $side === 'ar',
        'invoice-print-ar' => $side === 'ar',
    ]) @if ($side === 'ar') dir="rtl" lang="ar" @endif>
        @foreach ($lines as $line)
            <p class="invoice-print-seller-header-ids__line m-0">
                <span class="invoice-print-seller-header-ids__label">{{ $line['label'] }}</span>
                <span class="invoice-print-seller-header-ids__value" dir="ltr">{!! $line['value_html'] !!}</span>
            </p>
        @endforeach
    </div>
@endif