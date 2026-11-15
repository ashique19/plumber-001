@props(['class' => 'invoice-sar-symbol'])

<span {{ $attributes->merge(['class' => $class]) }} role="img" aria-label="SAR">
    <img src="{{ asset('images/sar-riyal-symbol.svg') }}" alt="" class="invoice-sar-symbol__svg" width="14" height="16" loading="eager" decoding="sync" />
    <span class="invoice-sar-symbol__fallback">SAR</span>
</span>