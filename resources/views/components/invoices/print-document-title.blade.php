@props(['t'])

@php
    $isSimplified = (bool) ($t['is_simplified'] ?? false);
    $titleEn = $isSimplified ? __('pos.receipt_title_simplified_en') : __('pos.receipt_title_standard_en');
    $titleAr = $isSimplified ? __('pos.receipt_title_simplified_ar') : __('pos.receipt_title_standard_ar');
@endphp

<h1 {{ $attributes->class(['invoice-a4__title']) }}>
    {{ $titleEn }}
    <span class="invoice-a4__title-ar block">{{ $titleAr }}</span>
</h1>