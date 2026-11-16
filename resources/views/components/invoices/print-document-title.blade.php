@props(['t'])

@php
    $titles = \App\Support\Invoicing\DocumentPrintTitles::forContext($t);
    $classicLayout = (bool) ($t['classic_title_stack'] ?? false);
@endphp

@if ($classicLayout)
    <h1 {{ $attributes->class(['print-classic__title']) }}>
        <span class="print-classic__title-ar" dir="rtl">{{ $titles['ar'] }}</span>
        <span class="print-classic__title-en">{{ $titles['en'] }}</span>
    </h1>
@else
    <h1 {{ $attributes->class(['invoice-a4__title']) }}>
        <span class="invoice-a4__title-en">{{ $titles['en'] }}</span>
        <span class="invoice-a4__title-ar" dir="rtl">{{ $titles['ar'] }}</span>
    </h1>
@endif