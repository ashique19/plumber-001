@props([
    'documentNumber',
])

@php
    $pageEn = 'Page 1 / 1';
    $pageAr = 'صفحة 1 / 1';
    $label = trim("{$pageEn} · {$pageAr} — {$documentNumber}");
@endphp
<p
    {{ $attributes->class(['invoice-print-page-label']) }}
    data-print-page-label
    data-document-number="{{ $documentNumber }}"
    data-page-en="Page :current / :total"
    data-page-ar="صفحة :current / :total"
>
    {{ $label }}
</p>