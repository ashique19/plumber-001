@php
    /** @var array<string, mixed> $t */
    $na = (array) ($t['national_address'] ?? []);
    $structuredAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
    $sellerEnLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressEnPrintLines($na) : [];
@endphp
<section class="invoice-a4__header print-tpl-modern__seller">
    <div class="invoice-a4__seller-side invoice-a4__seller-side--en">
        <strong
            class="invoice-a4__seller-name-en"
            @if ($isPdf ?? false)
                style="{{ \App\Support\Invoicing\DocumentPdfEnLineFit::inlineStyle((string) $t['seller_en'], 74, 8) }}"
            @endif
        >{{ $t['seller_en'] }}</strong>
        @if ($sellerEnLines !== [])
            @foreach ($sellerEnLines as $line)
                <span
                    class="invoice-a4__seller-address-en__line"
                    @if ($isPdf ?? false)
                        style="{{ \App\Support\Invoicing\DocumentPdfEnLineFit::inlineStyle((string) $line, 74, 7.5) }}"
                    @endif
                >{{ $line }}</span>
            @endforeach
        @elseif ($t['address_en'])
            <p class="m-0">{{ $t['address_en'] }}</p>
        @endif
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