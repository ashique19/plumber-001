@props(['en', 'ar'])

<span class="invoice-print-label">
    <span class="invoice-print-label__en">{{ $en }}</span>
    <span class="invoice-print-label__ar invoice-print-ar" dir="rtl">{{ $ar }}</span>
</span>