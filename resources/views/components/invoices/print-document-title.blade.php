@props(['t'])

@php
    $titles = \App\Support\Invoicing\DocumentPrintTitles::forContext($t);
    $classicLayout = (bool) ($t['classic_title_stack'] ?? false);
@endphp

@php
    $isQuotation = ($t['document_kind'] ?? 'invoice') === 'quotation';
@endphp
@if ($classicLayout)
    <h1 {{ $attributes->class(['print-classic__title']) }}>
        <span class="print-classic__title-ar invoice-print-ar" dir="rtl">{{ $titles['ar'] }}</span>
        <span class="print-classic__title-en">{{ $titles['en'] }}</span>
        @if ($isQuotation && filled($titles['bn'] ?? null))
            <span class="print-classic__title-bn">{{ $titles['bn'] }}</span>
        @endif
    </h1>
@else
    <h1 {{ $attributes->class(['invoice-a4__title']) }}>
        <span class="invoice-a4__title-en">{{ $titles['en'] }}</span>
        <span class="invoice-a4__title-ar invoice-print-ar" dir="rtl">{{ $titles['ar'] }}</span>
        @if ($isQuotation && filled($titles['bn'] ?? null))
            <span class="invoice-a4__title-bn">{{ $titles['bn'] }}</span>
        @endif
    </h1>
@endif