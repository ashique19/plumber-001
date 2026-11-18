@php
    /** @var array<string, mixed> $t */
    use App\Support\Invoicing\SellerHeaderIds;

    $na = (array) ($t['national_address'] ?? []);
    $structuredAddressLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
    $sellerEnLines = $na !== [] ? \App\Support\BusinessProfile::formatNationalAddressEnPrintLines($na) : [];
    $enIdLines = SellerHeaderIds::linesForSide($t, 'en');
    $arIdLines = SellerHeaderIds::linesForSide($t, 'ar');
    $idRowCount = max(count($enIdLines), count($arIdLines));
@endphp
<section class="invoice-a4__header print-tpl-modern__seller">
    <table class="invoice-a4__header-table" role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr class="invoice-a4__header-row invoice-a4__header-row--main">
            <td class="invoice-a4__header-cell invoice-a4__header-cell--en" width="38%" valign="top">
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
                </div>
            </td>
            <td class="invoice-a4__header-cell invoice-a4__header-cell--logo" width="24%" valign="top">
                @if ($t['logo_url'])
                    <img src="{{ asset($t['logo_url']) }}" alt="" class="invoice-a4__logo">
                @endif
            </td>
            <td class="invoice-a4__header-cell invoice-a4__header-cell--ar" width="38%" valign="top">
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
                </div>
            </td>
        </tr>
        @for ($i = 0; $i < $idRowCount; $i++)
            @php
                $enLine = $enIdLines[$i] ?? null;
                $arLine = $arIdLines[$i] ?? null;
            @endphp
            <tr class="invoice-a4__header-row invoice-a4__header-row--ids">
                <td class="invoice-a4__header-cell invoice-a4__header-cell--en" valign="top">
                    @if ($enLine)
                        <p class="invoice-print-seller-header-ids__line m-0">
                            <span class="invoice-print-seller-header-ids__label">{{ $enLine['label'] }}</span>
                            <span class="invoice-print-seller-header-ids__value" dir="ltr">{!! $enLine['value_html'] !!}</span>
                        </p>
                    @endif
                </td>
                <td class="invoice-a4__header-cell invoice-a4__header-cell--logo" aria-hidden="true"></td>
                <td class="invoice-a4__header-cell invoice-a4__header-cell--ar invoice-print-ar" dir="rtl" lang="ar" valign="top">
                    @if ($arLine)
                        <p class="invoice-print-seller-header-ids__line m-0">
                            <span class="invoice-print-seller-header-ids__label">{{ $arLine['label'] }}</span>
                            <span class="invoice-print-seller-header-ids__value" dir="ltr">{!! $arLine['value_html'] !!}</span>
                        </p>
                    @endif
                </td>
            </tr>
        @endfor
    </table>
</section>