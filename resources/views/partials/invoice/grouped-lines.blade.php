@php
    use App\Support\Invoicing\InvoiceLineGrouper;

    $grouped = InvoiceLineGrouper::sectionsForDisplay($lines, $locale ?? app()->getLocale());
@endphp

<section class="{{ $sectionClass ?? 'receipt-lines' }}" aria-label="{{ $ariaLabel ?? __('pos.current_order') }}">
    @foreach ($grouped['sections'] as $section)
        @if ($grouped['uses_groups'] && ($section['title'] ?? '') !== '')
            <h3 class="{{ $headingClass ?? 'receipt-lines__heading' }}" @if($locale === 'ar') dir="rtl" @endif>
                {{ InvoiceLineGrouper::sectionHeading($section, $locale ?? app()->getLocale()) }}
            </h3>
        @endif
        @foreach ($section['lines'] as $line)
            @php
                $lineTotalInc = ((float) ($line['unit_price_ex_vat'] ?? 0) * (float) ($line['quantity'] ?? 1) - (float) ($line['discount_ex_vat'] ?? 0))
                    * (1 + (float) ($line['vat_rate'] ?? 15) / 100);
                $lineName = ($locale ?? app()->getLocale()) === 'ar' && ! empty($line['name_ar']) ? $line['name_ar'] : ($line['name'] ?? '');
            @endphp
            <div class="{{ $lineClass ?? 'receipt-line' }}">
                <span class="{{ $lineNameClass ?? 'receipt-line__name' }}">
                    {{ $lineName }}
                    × {{ $line['quantity'] ?? 1 }}
                </span>
                <span class="{{ $lineAmtClass ?? 'receipt-line__amt' }}">{{ number_format($lineTotalInc, 2) }}</span>
            </div>
        @endforeach
    @endforeach
</section>
