<?php

namespace App\Support\Invoicing;

use ArPHP\I18N\Arabic;
use DOMDocument;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

final class DocumentPdfRenderer
{
    public static function download(string $html, string $filename): Response
    {
        $binary = self::render($html);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.pdf';
        if (! str_ends_with(strtolower($safe), '.pdf')) {
            $safe .= '.pdf';
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safe.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function render(string $html): string
    {
        $wrapped = self::wrapHtml($html, forMpdf: true);

        try {
            return self::renderWithMpdf($wrapped);
        } catch (\Throwable) {
            $wrappedDompdf = self::wrapHtml($html, forMpdf: false);

            return self::renderWithDompdf($wrappedDompdf);
        }
    }

    public static function pageCountForHtml(string $html): int
    {
        return self::pageCountInBinary(self::render($html));
    }

    public static function pageCountInBinary(string $binary): int
    {
        if (preg_match_all('/\/Type\s*\/Page(?![a-z])/i', $binary, $matches)) {
            return max(1, count($matches[0]));
        }

        return 1;
    }

    public static function wrapHtml(string $html, bool $forMpdf = true): string
    {
        $css = self::printCss();
        $body = $forMpdf ? $html : self::shapeArabic($html);
        $body = self::inlineSarSymbolsForPdf($body);

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .self::pdfFontFaceCss($forMpdf)
            .$css
            .self::pdfOverlayCss($forMpdf)
            .'</style></head><body>'
            .$body
            .'</body></html>';

        return self::localizeAssetUrls($wrapped);
    }

    private static function renderWithMpdf(string $wrapped): string
    {
        $tmp = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tmp);

        $defaults = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];
        $fontDirs[] = resource_path('fonts');

        $fontDefaults = (new FontVariables)->getDefaults();
        $fontData = $fontDefaults['fontdata'];
        if (is_file(resource_path('fonts/Amiri-Regular.ttf'))) {
            $fontData['amiri'] = [
                'R' => 'Amiri-Regular.ttf',
            ];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 9,
            'margin_right' => 9,
            'margin_top' => 7,
            'margin_bottom' => 9,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => 'dejavusans',
            'autoArabic' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'tempDir' => $tmp,
        ]);

        $mpdf->WriteHTML($wrapped);

        return $mpdf->Output('', 'S');
    }

    private static function renderWithDompdf(string $wrapped): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', self::amiriAvailable() ? 'AmiriPdf' : 'DejaVu Sans');
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($wrapped, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function amiriAvailable(): bool
    {
        return is_file(resource_path('fonts/Amiri-Regular.ttf'));
    }

    private static function pdfFontFaceCss(bool $forMpdf): string
    {
        if ($forMpdf) {
            return '';
        }

        $fontPath = resource_path('fonts/Amiri-Regular.ttf');

        return is_file($fontPath)
            ? '@font-face{font-family:AmiriPdf;src:url(\'file://'.$fontPath.'\') format(\'truetype\');font-weight:normal;font-style:normal;}'
            : '';
    }

    private static function pdfOverlayCss(bool $forMpdf): string
    {
        $latin = $forMpdf ? 'dejavusans, sans-serif' : 'DejaVu Sans,sans-serif';
        $arFont = $forMpdf
            ? (self::amiriAvailable() ? 'amiri, dejavusans, sans-serif' : 'dejavusans, sans-serif')
            : (self::amiriAvailable() ? 'AmiriPdf,DejaVu Sans,sans-serif' : 'DejaVu Sans,sans-serif');

        $arSelectors = '.invoice-print-root .invoice-print-ar,.invoice-print-root [dir=rtl],.invoice-print-root .ar,.invoice-a4__title-ar,.invoice-a4__desc-ar,.invoice-a4__footer-ar,.invoice-a4__seller-side--ar,.invoice-print-label__ar,.invoice-print-labeled-row__ar,.invoice-a4__buyer-table-label.ar,.invoice-a4__buyer-name-ar,.invoice-a4__table th .ar,.invoice-print-th-label__ar,.invoice-print-totals__ar,.print-classic__title-ar,.print-classic__cell-label.ar,.classic-party__name-ar,.invoice-print-words-block__ar,.print-classic__lines th .ar';

        return '.invoice-print-toolbar,.no-print{display:none!important}'
            .'html,body,.invoice-print-root,.invoice-print-root *{font-family:'.$latin.'!important;font-size:9.5pt;line-height:1.35;color:#111}'
            .$arSelectors.'{font-family:'.$arFont.'!important;direction:rtl!important;text-align:right!important;unicode-bidi:isolate}'
            .'.invoice-pdf-ltr-digits{direction:ltr!important;unicode-bidi:embed!important;font-family:'.$latin.'!important}'
            .'.invoice-a4__title-bn,.print-classic__title-bn{display:none!important}'
            .'.invoice-print-root{padding:3mm 6mm;font-size:9.5pt;max-width:none}'
            .'.invoice-print-root--pdf{padding:3mm 6mm 4mm!important}'
            .'.invoice-sar-symbol{display:inline!important;white-space:nowrap!important;line-height:1!important}'
            .'.invoice-sar-symbol__svg{display:inline-block!important;width:0.95em!important;height:1em!important;vertical-align:middle!important;margin:0!important}'
            .'.invoice-sar-symbol__fallback{display:none!important}'
            .'.invoice-print-money{display:inline!important;white-space:nowrap!important}'
            .'.invoice-print-totals__ar{white-space:normal!important;overflow-wrap:anywhere!important;font-size:8.5pt!important}'
            .'.invoice-print-totals__en{font-size:8.5pt!important;font-family:'.$latin.'!important}'
            .'.invoice-print-totals__value{white-space:nowrap!important;direction:ltr!important;text-align:end!important;font-size:9.5pt!important;font-family:'.$latin.'!important}'
            .'.invoice-print-totals td{padding:4px 5px!important}'
            .'.invoice-a4__buyer-table-label{font-family:'.$latin.'!important;font-weight:600!important}'
            .'.print-tpl-modern__totals-net td{border-top:1px solid #1a1a1a!important;padding-top:5px!important}'
            .'.invoice-print-root--pdf .invoice-a4__header{display:table!important;width:100%!important;table-layout:fixed!important;border-bottom:1px solid #1a1a1a;margin-bottom:5px;padding-bottom:4px}'
            .'.invoice-print-root--pdf .invoice-a4__header>div{display:table-cell!important;vertical-align:top;width:33%!important;padding:0 4px!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side{font-size:9pt!important;line-height:1.35!important;margin:0!important;word-wrap:break-word!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side p{margin:0!important;padding:0!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side--ar{text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__title{margin:4px 0;font-size:14pt!important;font-weight:700}'
            .'.invoice-print-root--pdf .print-tpl-modern__rule{margin:0 0 5px!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table{width:100%!important;margin-bottom:5px;font-size:8.5pt;border-collapse:collapse!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table th,.invoice-print-root--pdf .invoice-a4__buyer-table td{padding:4px 5px!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table tbody tr{border-bottom:1px solid #ccc!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split{width:100%!important;overflow:hidden!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-col{float:left!important;width:48%!important;padding:0 6px 0 0!important;box-sizing:border-box!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-col+.invoice-a4__buyer-table-split-col{float:right!important;padding:0 0 0 6px!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row{clear:both!important;margin:0 0 4px!important;overflow:hidden!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label{display:inline-block!important;width:38%!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-value{display:inline-block!important;width:32%!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label.ar{display:inline-block!important;width:28%!important;text-align:right!important;vertical-align:top!important}'
            .'.invoice-a4__buyer-table-value--nowrap{white-space:nowrap!important;overflow:visible!important;overflow-wrap:normal!important;word-break:normal!important;font-size:8.5pt!important}'
            .'.invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label{white-space:nowrap!important;font-size:8pt!important}'
            .'.invoice-a4__buyer-table-split-row{grid-template-columns:24% 1fr 26%!important;align-items:center!important}'
            .'.print-tpl-modern__footer-names{display:grid!important;grid-template-columns:1fr 1fr!important;text-align:start!important}'
            .'.print-tpl-modern__footer-names .invoice-a4__footer-ar{text-align:right!important}'
            .'.print-tpl-modern__words{align-items:flex-start!important}'
            .'.print-tpl-modern__vat-pct{text-align:right!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines{font-size:9pt;margin-bottom:5px;width:100%!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines th,.invoice-print-root--pdf .print-tpl-modern__lines td{padding:4px 5px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead th .ar{font-size:8pt!important}'
            .'.invoice-print-labeled-row{display:grid!important;grid-template-columns:22% 1fr 24%!important;align-items:center!important;width:100%!important;gap:4px 6px!important}'
            .'.invoice-print-labeled-row__en{text-align:left!important;font-family:'.$latin.'!important;font-weight:600!important}'
            .'.invoice-print-labeled-row__value{text-align:center!important;font-family:'.$latin.'!important}'
            .'.invoice-print-labeled-row__ar{text-align:right!important}'
            .'.invoice-print-meta-grid{width:100%!important;border-collapse:collapse!important;margin-bottom:6px!important}'
            .'.invoice-print-meta-grid td{width:50%!important;padding:2px 4px!important;vertical-align:middle!important}'
            .'.invoice-a4__table--product-lines th.col-index,.invoice-a4__table--product-lines td.col-index{text-align:center!important}'
            .'.invoice-a4__table--product-lines th.col-num,.invoice-a4__table--product-lines td.col-num{text-align:right!important}'
            .'.invoice-a4__table--product-lines th.col-desc,.invoice-a4__table--product-lines td.col-desc{text-align:left!important}'
            .'.invoice-print-root--pdf td.tabular-nums{white-space:nowrap!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__words{margin-top:4px;margin-left:auto!important;width:100%!important;min-width:240px!important;max-width:360px!important;border:1px solid #1a1a1a!important;padding:6px 8px!important;box-sizing:border-box!important;font-size:8.5pt;line-height:1.35;page-break-inside:avoid}'
            .'.invoice-print-root--pdf .print-tpl-modern__sheet-footer,.invoice-print-root--pdf .invoice-a4__footer.print-only{display:none!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing{display:block!important;width:100%!important;margin-top:8px!important;page-break-inside:avoid!important;overflow:hidden!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__qr-wrap{float:left!important;width:105px!important;padding-right:10px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__qr-wrap--empty{display:none!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__totals{float:right!important;width:calc(100% - 115px)!important;margin-left:0!important;min-width:240px!important;max-width:none!important}'
            .'.invoice-print-totals{display:table!important;width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.invoice-print-totals tr{display:table-row!important}'
            .'.invoice-print-totals td{display:table-cell!important;vertical-align:middle!important}'
            .'.print-tpl-modern__totals{min-width:240px;max-width:360px;font-size:9pt!important}'
            .'.print-classic__totals-wrap{display:flex;justify-content:flex-end}'
            .'.print-classic__totals{display:table!important;min-width:62%;margin-left:auto}'
            .'.print-classic__closing{display:table!important;width:100%!important}'
            .'.invoice-print-words-block{display:flex!important;align-items:flex-start!important;flex-wrap:nowrap!important;gap:8px 12px!important}'
            .'.invoice-print-words-block__en,.invoice-print-words-block__ar{display:block!important;flex:1 1 0!important;min-width:0!important;margin:0!important}'
            .'.invoice-a4__qr-img,.print-tpl-modern__qr-wrap img,.print-classic__top-right img.invoice-a4__qr-img{width:96px!important;height:96px!important}'
            .'.invoice-a4__qr canvas{display:none!important}'
            .'.print-tpl-modern,.print-tpl-classic{padding-bottom:3mm}'
            .'.invoice-a4__logo,.print-classic__logo{max-height:48px}'
            .'@page{margin:7mm 9mm 9mm}';
    }

    private static function printCss(): string
    {
        $files = [
            resource_path('css/invoice-print.css'),
            resource_path('css/print-template-modern.css'),
            resource_path('css/print-template-classic.css'),
        ];

        $css = '';
        foreach ($files as $file) {
            if (is_file($file)) {
                $css .= (string) file_get_contents($file);
            }
        }

        return $css;
    }

    private static function shapeArabic(string $html): string
    {
        if (! preg_match('/\p{Arabic}/u', $html)) {
            return $html;
        }

        $arabic = new Arabic;
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()') ?: [] as $node) {
            $parent = $node->parentNode;
            if ($parent instanceof \DOMElement && in_array(strtolower($parent->tagName), ['script', 'style'], true)) {
                continue;
            }

            if ($parent instanceof \DOMElement && str_contains((string) $parent->getAttribute('class'), 'invoice-pdf-ltr-digits')) {
                continue;
            }

            $text = $node->nodeValue ?? '';
            if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
                continue;
            }

            $node->nodeValue = $arabic->utf8Glyphs($text, max(120, mb_strlen($text)), false, false);
        }

        $out = $dom->saveHTML();

        return is_string($out) ? $out : $html;
    }

    private static function sarSymbolDataUri(): string
    {
        static $uri = null;
        if ($uri !== null) {
            return $uri;
        }

        $path = resource_path('images/sar-riyal-symbol.svg');
        $svg = is_file($path) ? (string) file_get_contents($path) : '';
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return $uri;
    }

    private static function inlineSarSymbolsForPdf(string $html): string
    {
        if (! str_contains($html, 'invoice-sar-symbol__svg')) {
            return $html;
        }

        $img = '<img class="invoice-sar-symbol__svg" alt="" src="'.self::sarSymbolDataUri().'" style="width:1em;height:1em;vertical-align:-0.12em;display:inline-block" />';

        return (string) preg_replace(
            '/<svg[^>]*class="[^"]*invoice-sar-symbol__svg[^"]*"[^>]*>.*?<\/svg>/s',
            $img,
            $html
        );
    }

    private static function localizeAssetUrls(string $html): string
    {
        return (string) preg_replace_callback(
            '/src="(https?:\/\/[^"]+)"/',
            function (array $match): string {
                $path = parse_url($match[1], PHP_URL_PATH);
                if (! is_string($path) || $path === '') {
                    return $match[0];
                }
                $full = public_path(ltrim($path, '/'));
                if (! is_file($full)) {
                    return $match[0];
                }

                return 'src="file://'.$full.'"';
            },
            $html
        );
    }
}