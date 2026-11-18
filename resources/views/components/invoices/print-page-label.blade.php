@props([
    'documentNumber',
])

<p
    {{ $attributes->class(['invoice-print-page-label']) }}
    data-document-number="{{ $documentNumber }}"
    dir="ltr"
>
    <span class="invoice-print-page-label__screen no-print">{{ $documentNumber }}</span>
    <span class="invoice-print-page-label__print print-only">
        <span class="invoice-print-page-label__en" aria-hidden="true"></span><span class="invoice-print-page-label__sep" aria-hidden="true"> · </span><span class="invoice-print-page-label__ar-word invoice-print-ar" dir="rtl" lang="ar">صفحة</span><span class="invoice-print-page-label__ar-num" dir="ltr" aria-hidden="true"></span><span class="invoice-print-page-label__sep" aria-hidden="true"> — </span><span class="invoice-print-page-label__doc">{{ $documentNumber }}</span>
    </span>
</p>