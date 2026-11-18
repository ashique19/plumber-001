@php
    /** @var array<string, mixed> $t */
    use App\Support\BusinessProfile;
    use App\Support\Invoicing\BuyerPrintBlock;
    use App\Support\Invoicing\DocumentPrintTitles;
    use App\Support\Invoicing\InvoicePrintMetaRows;
    use App\Support\Invoicing\InvoicePrintQrCaption;
    use App\Support\Invoicing\InvoicePrintTotalsBlock;

    $titles = DocumentPrintTitles::forContext($t);
    $na = (array) ($t['national_address'] ?? []);
    $sellerEnLines = $na !== [] ? BusinessProfile::formatNationalAddressEnPrintLines($na) : [];
    if ($sellerEnLines === [] && filled($t['address_en'] ?? null)) {
        $sellerEnLines = [(string) $t['address_en']];
    }
    $sellerArLines = $na !== [] ? BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
    $metaRows = InvoicePrintMetaRows::modernInvoiceMeta($t);
    $metaPairs = array_chunk($metaRows, 2);
    $buyerRows = BuyerPrintBlock::rows($t['customer'] ?? null);
    $tot = $t['totals'];
    $sarSrc = 'file://'.resource_path('images/sar-riyal-symbol.png');
    $logoSrc = filled($t['logo_url'] ?? null)
        ? 'file://'.public_path(ltrim((string) $t['logo_url'], '/'))
        : null;
    $showQr = ($t['show_qr'] ?? true) && filled($t['qr_tlv_base64'] ?? null);
    $qrSrc = filled($t['qr_png'] ?? null) ? (string) $t['qr_png'] : null;
    $lbl = 'font-family:dejavusans;font-size:8pt;color:#666666;';
    $val = 'font-family:dejavusans;font-size:9pt;color:#111111;';
    $arLbl = 'font-family:amiri;font-size:8pt;color:#666666;';
    $arVal = 'font-family:amiri;font-size:9pt;color:#111111;';
    $border = 'border:1px solid #1a1a1a;';
    $cellPad = 'padding:3px 4px;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $titles['en'] }} {{ $t['document_number'] }}</title>
    <style>
        body { margin: 0; padding: 0; font-family: dejavusans; font-size: 9pt; color: #111111; line-height: 1.35; }
        table { border-collapse: collapse; }
        .ar { font-family: amiri; direction: rtl; text-align: right; unicode-bidi: embed; }
        .ar-center { font-family: amiri; direction: rtl; text-align: center; unicode-bidi: embed; }
        .num { white-space: nowrap; }
        .invoice-a4__buyer-name-en { font-family: dejavusans; font-size: 9pt; color: #111111; }
        .invoice-a4__buyer-name-ar { font-family: amiri; font-size: 9pt; direction: rtl; text-align: right; color: #111111; }
        .invoice-a4__buyer-address-en__line { display: block; font-family: dejavusans; font-size: 9pt; line-height: 1.3; color: #111111; margin: 0; }
    </style>
</head>
<body>

{{-- Seller header: EN | logo | AR --}}
<table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:4px;border-bottom:3px double #1a1a1a;">
    <tr>
        <td width="33%" valign="top" style="width:33%;vertical-align:top;padding:0 3px 4px 0;">
            <div style="font-family:dejavusans;font-size:9pt;font-weight:bold;line-height:1.25;max-width:62mm;">{{ $t['seller_en'] }}</div>
            @foreach ($sellerEnLines as $line)
                <p style="margin:1px 0 0;font-size:9pt;line-height:1.35;">{{ $line }}</p>
            @endforeach
            @if (filled($t['trn'] ?? null))
                <p style="margin:3px 0 0;{{ $lbl }}">VAT No. <span style="{{ $val }}">{{ $t['trn'] }}</span></p>
            @endif
            @if (filled($t['cr_number'] ?? null))
                <p style="margin:1px 0 0;{{ $lbl }}">CR No. <span style="{{ $val }}">{{ $t['cr_number'] }}</span></p>
            @endif
        </td>
        <td width="34%" valign="top" align="center" style="width:34%;vertical-align:top;text-align:center;padding:0 3px 4px;">
            @if ($logoSrc && is_file(public_path(ltrim((string) ($t['logo_url'] ?? ''), '/'))))
                <img src="{{ $logoSrc }}" alt="" style="max-height:38px;max-width:52mm;">
            @endif
        </td>
        <td width="33%" valign="top" align="right" style="width:33%;vertical-align:top;text-align:right;padding:0 0 4px 3px;">
            <div class="ar" lang="ar" style="font-family:amiri;font-size:9pt;font-weight:bold;line-height:1.3;">{{ $t['seller_ar'] }}</div>
            @foreach ($sellerArLines as $line)
                <p class="ar" lang="ar" style="margin:1px 0 0;font-size:9pt;line-height:1.35;">{!! $line !!}</p>
            @endforeach
            @if (filled($t['trn'] ?? null))
                <p class="ar" lang="ar" style="margin:3px 0 0;{{ $arLbl }}">الرقم الضريبي <span dir="ltr" style="{{ $val }}font-family:dejavusans;">{{ $t['trn'] }}</span></p>
            @endif
            @if (filled($t['cr_number'] ?? null))
                <p class="ar" lang="ar" style="margin:1px 0 0;{{ $arLbl }}">السجل التجاري <span dir="ltr" style="{{ $val }}font-family:dejavusans;">{{ $t['cr_number'] }}</span></p>
            @endif
        </td>
    </tr>
</table>

{{-- Title --}}
<table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:3px 0 6px;">
    <tr>
        <td align="center" style="text-align:center;font-size:13pt;font-weight:bold;line-height:1.3;">
            <span style="font-family:dejavusans;">{{ $titles['en'] }}</span>
            <span class="ar" lang="ar" style="font-family:amiri;font-size:12pt;display:block;margin-top:2px;">{{ $titles['ar'] }}</span>
        </td>
    </tr>
</table>

{{-- Meta grid (2 columns of label | value | AR label) --}}
@if ($metaRows !== [])
    <table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:6px;">
        @foreach ($metaPairs as $pair)
            <tr>
                @foreach ($pair as $row)
                    <td width="50%" valign="top" style="width:50%;vertical-align:top;padding:1px 3px;">
                        <table width="100%" cellspacing="0" cellpadding="0" style="width:100%;">
                            <tr>
                                <td width="20%" style="width:20%;{{ $lbl }}{{ $cellPad }}white-space:nowrap;">{{ $row['en'] }}</td>
                                <td width="50%" style="width:50%;{{ $val }}{{ $cellPad }}">@if (! empty($row['html'])){!! $row['html'] !!}@else{{ $row['value'] ?? '' }}@endif</td>
                                <td width="30%" class="ar" lang="ar" style="width:30%;{{ $arLbl }}{{ $cellPad }}">{{ $row['ar'] }}</td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                @if (count($pair) === 1)
                    <td width="50%" style="width:50%;"></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif

{{-- Customer table (standard) --}}
@if (count($buyerRows) > 0)
    <table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:6px;{{ $border }}">
        @foreach ($buyerRows as $row)
            <tr>
                <td width="22%" style="width:22%;{{ $border }}{{ $lbl }}{{ $cellPad }}white-space:nowrap;">{{ $row['en'] }}</td>
                <td width="56%" style="width:56%;{{ $border }}{{ $val }}{{ $cellPad }}vertical-align:middle;">
                    @if (! empty($row['html']))
                        {!! $row['html'] !!}
                    @else
                        {{ $row['value'] ?? '' }}
                    @endif
                </td>
                <td width="22%" class="ar" lang="ar" style="width:22%;{{ $border }}{{ $arLbl }}{{ $cellPad }}">{{ $row['ar'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Line items --}}
<table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:6px;{{ $border }}font-size:8.5pt;">
    <thead>
        <tr style="background-color:#e8e8e8;">
            <th width="6%" style="{{ $border }}{{ $cellPad }}text-align:center;font-size:8pt;">
                <span style="font-family:dejavusans;font-weight:bold;">#</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">م</span>
            </th>
            <th style="{{ $border }}{{ $cellPad }}text-align:left;">
                <span style="font-family:dejavusans;font-weight:bold;">Description</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">الوصف</span>
            </th>
            <th width="8%" style="{{ $border }}{{ $cellPad }}text-align:right;">
                <span style="font-family:dejavusans;font-weight:bold;">Qty</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">الكمية</span>
            </th>
            <th width="11%" style="{{ $border }}{{ $cellPad }}text-align:right;">
                <span style="font-family:dejavusans;font-weight:bold;">Price</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">السعر</span>
            </th>
            <th width="13%" style="{{ $border }}{{ $cellPad }}text-align:right;">
                <span style="font-family:dejavusans;font-weight:bold;">Taxable amount</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">المبلغ الخاضع للضريبة</span>
            </th>
            <th width="12%" style="{{ $border }}{{ $cellPad }}text-align:right;font-size:7pt;">
                <span style="font-family:dejavusans;font-weight:bold;white-space:nowrap;">VAT amount (+%)</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">القيمة المضافة</span>
            </th>
            <th width="12%" style="{{ $border }}{{ $cellPad }}text-align:right;">
                <span style="font-family:dejavusans;font-weight:bold;">Line amount</span><br>
                <span class="ar" lang="ar" style="{{ $arLbl }}">المجموع</span>
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($t['lines'] as $line)
            <tr>
                <td style="{{ $border }}{{ $cellPad }}text-align:center;" class="num">{{ $line['line_no'] }}</td>
                <td style="{{ $border }}{{ $cellPad }}text-align:left;vertical-align:top;">
                    <span style="{{ $val }}">{{ $line['name_en'] }}</span>
                    @if (filled($line['name_ar'] ?? null))
                        <br><span class="ar" lang="ar" style="{{ $arVal }}font-size:8pt;">{{ $line['name_ar'] }}</span>
                    @endif
                </td>
                <td style="{{ $border }}{{ $cellPad }}text-align:right;" class="num">{{ $line['quantity_display'] }}</td>
                <td style="{{ $border }}{{ $cellPad }}text-align:right;" class="num"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($line['unit_price_ex_vat'], 2) }}</td>
                <td style="{{ $border }}{{ $cellPad }}text-align:right;" class="num"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($line['taxable_ex_vat'], 2) }}</td>
                <td style="{{ $border }}{{ $cellPad }}text-align:right;vertical-align:middle;" class="num">
                    <img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($line['vat_amount'], 2) }}<br>
                    <span style="font-size:7pt;color:#666666;">{{ number_format($line['vat_rate'], 0) }}%</span>
                </td>
                <td style="{{ $border }}{{ $cellPad }}text-align:right;" class="num"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($line['line_amount_inc_vat'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Closing: QR + totals --}}
<table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-top:4px;">
    <tr>
        @if ($showQr && $qrSrc)
            <td width="38%" valign="top" style="width:38%;vertical-align:top;padding-right:6px;">
                <div style="text-align:center;max-width:48mm;">
                    <img src="{{ $qrSrc }}" alt="" width="113" height="113" style="width:30mm;height:30mm;display:block;margin:0 auto;">
                    @foreach (InvoicePrintQrCaption::enLines() as $enLine)
                        <p style="margin:3px 0 0;font-size:7.5pt;line-height:1.25;color:#666666;text-align:center;font-family:dejavusans;">{{ $enLine }}</p>
                    @endforeach
                    <div style="margin-top:2px;text-align:center;">
                        @foreach (InvoicePrintQrCaption::arLines() as $arLine)
                            <div class="ar-center" lang="ar" style="margin:0;font-size:7.5pt;line-height:1.25;color:#666666;">{{ $arLine }}</div>
                        @endforeach
                    </div>
                </div>
            </td>
        @endif
        <td valign="top" align="right" style="vertical-align:top;text-align:right;">
            <table width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:96mm;margin-left:auto;font-size:9pt;">
                <tr>
                    <td width="32%" style="{{ $lbl }}{{ $cellPad }}text-align:right;">Subtotal</td>
                    <td width="38%" class="ar" lang="ar" style="{{ $arLbl }}{{ $cellPad }}text-align:right;">المجموع الفرعي</td>
                    <td width="30%" class="num" style="{{ $val }}{{ $cellPad }}text-align:right;"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($tot['subtotal_ex_vat'], 2) }}</td>
                </tr>
                <tr>
                    <td style="{{ $lbl }}{{ $cellPad }}text-align:right;">Total VAT</td>
                    <td class="ar" lang="ar" style="{{ $arLbl }}{{ $cellPad }}text-align:right;white-space:nowrap;">إجمالي ضريبة القيمة المضافة</td>
                    <td class="num" style="{{ $val }}{{ $cellPad }}text-align:right;"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($tot['total_vat'], 2) }}</td>
                </tr>
                <tr>
                    <td style="{{ $lbl }}{{ $cellPad }}text-align:right;font-weight:bold;">Total</td>
                    <td class="ar" lang="ar" style="{{ $arLbl }}{{ $cellPad }}text-align:right;font-weight:bold;white-space:nowrap;">المجموع شامل القيمة المضافة</td>
                    <td class="num" style="{{ $val }}{{ $cellPad }}text-align:right;font-weight:bold;"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($tot['total_inc_vat'], 2) }}</td>
                </tr>
                @if (InvoicePrintTotalsBlock::shouldShowNetDue($t, $tot))
                    <tr>
                        <td style="{{ $lbl }}{{ $cellPad }}text-align:right;font-weight:bold;border-top:1px solid #1a1a1a;">Net due</td>
                        <td class="ar" lang="ar" style="{{ $arLbl }}{{ $cellPad }}text-align:right;font-weight:bold;border-top:1px solid #1a1a1a;">المبلغ المستحق</td>
                        <td class="num" style="{{ $val }}{{ $cellPad }}text-align:right;font-weight:bold;border-top:1px solid #1a1a1a;"><img src="{{ $sarSrc }}" alt="" style="height:0.85em;vertical-align:baseline;border:0;"> {{ number_format($t['net_due_inc_vat'], 2) }}</td>
                    </tr>
                @endif
            </table>
            <table width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:96mm;margin:4px 0 0 auto;{{ $border }}">
                <tr>
                    <td width="50%" valign="top" style="width:50%;{{ $cellPad }}font-size:7.5pt;line-height:1.25;vertical-align:top;">
                        @if (filled($t['amount_words_en'] ?? null))
                            {{ $t['amount_words_en'] }}
                        @endif
                    </td>
                    <td width="50%" class="ar" lang="ar" valign="top" style="width:50%;{{ $cellPad }}font-size:7.5pt;line-height:1.25;text-align:right;vertical-align:top;">
                        @if (filled($t['amount_words_ar'] ?? null))
                            {{ $t['amount_words_ar'] }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Footer --}}
<table width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin-top:8px;font-size:8pt;color:#666666;">
    <tr>
        <td width="33%" style="width:33%;text-align:left;font-family:dejavusans;">{{ $t['seller_en'] }}</td>
        <td width="34%" align="center" style="width:34%;text-align:center;font-family:dejavusans;">1 / 1</td>
        <td width="33%" class="ar" lang="ar" align="right" style="width:33%;text-align:right;font-family:amiri;">{{ $t['seller_ar'] }}</td>
    </tr>
</table>

</body>
</html>