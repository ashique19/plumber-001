@props([
    'documentNumber',
])

@php
    $pageOfOne = __('invoices.print_page_of', ['current' => '1', 'total' => '1']);
    $label = trim($pageOfOne.' - '.$documentNumber);
@endphp
<p
    {{ $attributes->class(['invoice-print-page-label']) }}
    data-print-page-label
    data-document-number="{{ $documentNumber }}"
    data-page-of="{{ __('invoices.print_page_of') }}"
>
    {{ $label }}
</p>
