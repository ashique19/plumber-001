@props([
    'documentNumber',
])

<p
    {{ $attributes->class(['invoice-print-page-label']) }}
    data-document-number="{{ $documentNumber }}"
    dir="ltr"
>
    <span class="invoice-print-page-label__screen no-print">{{ $documentNumber }}</span>
    <span class="invoice-print-page-label__print print-only">{{ $documentNumber }}</span>
</p>