{{-- Dompdf-only seller header rows (included when $isPdf is true). --}}
@php
    $na = (array) ($t['national_address'] ?? []);
    $arDompdfAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArDompdfPlainLines($na) : [];
@endphp
<tr class="invoice-a4__header-row invoice-a4__header-row--main">
    <td class="invoice-a4__header-cell invoice-a4__header-cell--en" width="40%" valign="top">
        <div class="invoice-pdf-seller-en-compact">
            <div class="invoice-pdf-seller-line invoice-pdf-seller-line--name invoice-pdf-seller-en-name">{{ $t['seller_en'] }}</div>
            @if ($sellerEnLines !== [])
                @foreach ($sellerEnLines as $line)
                    <div class="invoice-pdf-seller-line invoice-pdf-seller-en-body">{{ $line }}</div>
                @endforeach
            @elseif ($t['address_en'] ?? null)
                <div class="invoice-pdf-seller-line invoice-pdf-seller-en-body">{{ $t['address_en'] }}</div>
            @endif
            @foreach ($enIdLines as $enLine)
                <div class="invoice-pdf-seller-line invoice-pdf-seller-en-body">
                    <span class="invoice-print-seller-header-ids__label">{{ $enLine['label'] }}</span>
                    <span class="invoice-print-seller-header-ids__value" dir="ltr">{{ $enLine['value'] }}</span>
                </div>
            @endforeach
        </div>
    </td>
    <td class="invoice-a4__header-cell invoice-a4__header-cell--logo" width="20%" valign="top">
        @if ($t['logo_url'])
            <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo invoice-a4__logo--header">
        @endif
    </td>
    <td class="invoice-a4__header-cell invoice-a4__header-cell--ar" width="40%" valign="top">
        <div class="invoice-pdf-seller-ar-compact" dir="rtl" lang="ar">
            <div class="invoice-pdf-seller-line invoice-pdf-seller-line--name invoice-pdf-seller-ar-name">{{ $t['seller_ar'] }}</div>
            @if ($arDompdfAddressLines !== [])
                @foreach ($arDompdfAddressLines as $line)
                    <div class="invoice-pdf-seller-line invoice-pdf-seller-ar-body">{!! $line !!}</div>
                @endforeach
            @elseif ($structuredAddressLines !== [])
                @foreach ($structuredAddressLines as $line)
                    <div class="invoice-pdf-seller-line invoice-pdf-seller-ar-body">{!! $line !!}</div>
                @endforeach
            @elseif ($t['address_ar_html'] ?? null)
                <div class="invoice-pdf-seller-line invoice-pdf-seller-ar-body">{!! $t['address_ar_html'] !!}</div>
            @elseif ($t['address_ar'] ?? null)
                <div class="invoice-pdf-seller-line invoice-pdf-seller-ar-body">{{ $t['address_ar'] }}</div>
            @endif
            @foreach ($arIdLines as $arLine)
                <div class="invoice-pdf-seller-line invoice-pdf-seller-ar-body">
                    <span class="invoice-print-seller-header-ids__label">{{ $arLine['label'] }}</span>
                    <span class="invoice-print-seller-header-ids__value" dir="ltr">{{ $arLine['value'] }}</span>
                </div>
            @endforeach
        </div>
    </td>
</tr>