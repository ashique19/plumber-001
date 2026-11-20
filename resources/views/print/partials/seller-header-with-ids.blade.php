@php
    /** @var array<string, mixed> $t */
    use App\Support\Invoicing\SellerHeaderIds;

    $na = (array) ($t['national_address'] ?? []);
    $structuredAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
    $sellerEnLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressEnPrintLines($na) : [];
    $enIdLines = SellerHeaderIds::linesForSide($t, 'en');
    $arIdLines = SellerHeaderIds::linesForSide($t, 'ar');
@endphp
<section class="invoice-a4__header print-tpl-modern__seller">
    <table class="invoice-a4__header-table" role="presentation" width="100%" cellspacing="0" cellpadding="0">
        @if ($isPdf ?? false)
            @include('print.partials.seller-header-with-ids-dompdf', [
                't' => $t,
                'sellerEnLines' => $sellerEnLines,
                'structuredAddressLines' => $structuredAddressLines,
                'enIdLines' => $enIdLines,
                'arIdLines' => $arIdLines,
            ])
        @else
            <tr class="invoice-a4__header-row invoice-a4__header-row--main">
                <td class="invoice-a4__header-cell invoice-a4__header-cell--en" width="38%" valign="top">
                    <div class="invoice-a4__seller-side invoice-a4__seller-side--en invoice-a4__seller-en-stack">
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
                        @foreach ($enIdLines as $enLine)
                            <p class="invoice-print-seller-header-ids__line m-0">
                                <span class="invoice-print-seller-header-ids__label">{{ $enLine['label'] }}</span>
                                <span class="invoice-print-seller-header-ids__value" dir="ltr">{!! $enLine['value_html'] !!}</span>
                            </p>
                        @endforeach
                    </div>
                </td>
                <td class="invoice-a4__header-cell invoice-a4__header-cell--logo" width="24%" valign="top">
                    @if ($t['logo_url'])
                        <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo">
                    @endif
                </td>
                <td class="invoice-a4__header-cell invoice-a4__header-cell--ar" width="38%" valign="top">
                    <div class="invoice-a4__seller-side invoice-a4__seller-side--ar invoice-print-ar invoice-a4__seller-ar-stack" dir="rtl" lang="ar">
                        <strong class="invoice-a4__seller-name-ar">{{ $t['seller_ar'] }}</strong>
                        @if ($structuredAddressLines !== [])
                            <div class="invoice-national-address-ar-lines">
                                @foreach ($structuredAddressLines as $line)
                                    <div @class([
                                        'invoice-national-address-ar__line',
                                        'invoice-pdf-ar-address-line' => $isPdf ?? false,
                                    ])>{!! $line !!}</div>
                                @endforeach
                            </div>
                        @elseif ($t['address_ar_html'] ?? null)
                            <p class="m-0">{!! $t['address_ar_html'] !!}</p>
                        @elseif ($t['address_ar'])
                            <p class="m-0 invoice-print-ar" dir="rtl" lang="ar">{{ $t['address_ar'] }}</p>
                        @endif
                        @foreach ($arIdLines as $arLine)
                            <p class="invoice-print-seller-header-ids__line invoice-print-seller-header-ids__line--ar m-0">
                                <span class="invoice-print-seller-header-ids__label">{{ $arLine['label'] }}</span>
                                <span class="invoice-print-seller-header-ids__value" dir="ltr">{!! $arLine['value_html'] !!}</span>
                            </p>
                        @endforeach
                    </div>
                </td>
            </tr>
        @endif
    </table>
</section>