@props([
    'documentNumber',
])

@php
    $pageEn = 'Page 1 / 1';
    $pageAr = 'صفحة 1 / 1';
@endphp
<p
    {{ $attributes->class(['invoice-print-page-label']) }}
    data-print-page-label
    data-document-number="{{ $documentNumber }}"
    data-page-en="Page :current / :total"
    data-page-ar="صفحة :current / :total"
    dir="ltr"
>
    <span class="invoice-print-page-label__en" dir="ltr">{{ $pageEn }}</span><span class="invoice-print-page-label__sep" aria-hidden="true"> · </span><span class="invoice-print-page-label__ar invoice-print-ar" dir="rtl" lang="ar">{{ $pageAr }}</span><span class="invoice-print-page-label__sep" aria-hidden="true"> — </span><span class="invoice-print-page-label__doc" dir="ltr">{{ $documentNumber }}</span>
</p>