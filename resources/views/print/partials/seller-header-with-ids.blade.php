@php
    /** @var array<string, mixed> $t */
    $na = (array) ($t['national_address'] ?? []);
    $structuredAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
    $sellerEnAddressLines = $na !== []
        ? \App\Support\BusinessProfile::formatNationalAddressEnPrintLines($na)
        : (filled($t['address_en'] ?? null) ? [(string) $t['address_en']] : []);
@endphp
<section class="invoice-a4__header print-tpl-modern__seller">
    <div class="invoice-a4__seller-side">
        <strong class="invoice-a4__seller-name-en">{{ $t['seller_en'] }}</strong>
        @foreach ($sellerEnAddressLines as $line)
            <p class="m-0 invoice-a4__seller-address-en__line">{{ $line }}</p>
        @endforeach
        <x-invoices.print-seller-header-ids :t="$t" side="en" />
    </div>
    <div>
        @if ($t['logo_url'])
            <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo">
        @endif
    </div>
    <div class="invoice-a4__seller-side invoice-a4__seller-side--ar invoice-print-ar" dir="rtl">
        <strong>{{ $t['seller_ar'] }}</strong>
        @if ($structuredAddressLines !== [])
            <div @class(['invoice-national-address-ar-lines', 'invoice-national-address-ar--pdf' => $isPdf ?? false]) lang="ar">
                @foreach ($structuredAddressLines as $line)
                    <div @class([
                        'invoice-national-address-ar__line',
                        'invoice-pdf-ar-address-line' => $isPdf ?? false,
                        'invoice-pdf-ar-block' => $isPdf ?? false,
                    ]) dir="rtl" style="text-align:right">{!! $line !!}</div>
                @endforeach
            </div>
        @elseif ($t['address_ar_html'] ?? null)
            <p class="m-0">{!! $t['address_ar_html'] !!}</p>
        @elseif ($t['address_ar'])
            <p @class(['m-0', 'invoice-print-ar', 'invoice-pdf-ar-block invoice-national-address-ar--pdf' => $isPdf ?? false]) dir="rtl" lang="ar">{{ $t['address_ar'] }}</p>
        @endif
        <x-invoices.print-seller-header-ids :t="$t" side="ar" />
    </div>
</section>