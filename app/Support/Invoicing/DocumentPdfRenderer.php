<?php

namespace App\Support\Invoicing;

use ArPHP\I18N\Arabic;
use DOMDocument;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

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
        $wrapped = self::wrapHtml($html);

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

    public static function wrapHtml(string $html): string
    {
        $fontPath = resource_path('fonts/Amiri-Regular.ttf');
        $css = self::printCss();
        $fontFace = is_file($fontPath)
            ? '@font-face{font-family:AmiriPdf;src:url(\'file://'.$fontPath.'\') format(\'truetype\');font-weight:normal;font-style:normal;}'
            : '';
        $body = self::shapeArabic($html);
        $body = self::inlineSarSymbolsForDompdf($body);

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$fontFace
            .$css
            .self::pdfOverlayCss()
            .'</style></head><body>'
            .$body
            .'</body></html>';

        return self::localizeAssetUrls($wrapped);
    }

    private static function amiriAvailable(): bool
    {
        return is_file(resource_path('fonts/Amiri-Regular.ttf'));
    }

    private static function pdfOverlayCss(): string
    {
        $arFont = self::amiriAvailable() ? 'AmiriPdf,DejaVu Sans,sans-serif' : 'DejaVu Sans,sans-serif';
        $latin = 'DejaVu Sans,sans-serif';

        $arSelectors = '.invoice-print-root .invoice-print-ar,.invoice-print-root [dir=rtl],.invoice-print-root .ar,.invoice-a4__title-ar,.invoice-a4__desc-ar,.invoice-a4__footer-ar,.invoice-a4__seller-side--ar,.invoice-print-label__ar,.invoice-a4__buyer-table-label.ar,.invoice-a4__buyer-name-ar,.invoice-a4__table th .ar,.invoice-print-totals__ar,.print-classic__title-ar,.print-classic__cell-label.ar,.classic-party__name-ar,.invoice-print-words-block__ar,.print-classic__lines th .ar';

        return '.invoice-print-toolbar,.no-print{display:none!important}'
            .'html,body,.invoice-print-root{font-family:'.$latin.'!important;font-size:9.5pt;line-height:1.35;color:#111}'
            .'.invoice-print-root *{font-family:inherit}'
            .$arSelectors.'{font-family:'.$arFont.'!important;direction:rtl!important;text-align:right!important;unicode-bidi:isolate}'
            .'.invoice-pdf-ar-plaintext{font-family:'.$arFont.'!important;direction:rtl!important;text-align:right!important;unicode-bidi:plaintext!important;white-space:normal!important;line-height:1.35!important}'
            .'.invoice-pdf-ltr-digits{direction:ltr!important;unicode-bidi:embed!important;font-family:'.$latin.'!important}'
            .'.invoice-a4__title-bn,.print-classic__title-bn{display:none!important}'
            .'.invoice-print-root{padding:3mm 6mm;font-size:9.5pt;max-width:none}'
            .'.invoice-print-root--pdf{padding:3mm 6mm 4mm!important}'
            .'.invoice-sar-symbol{display:inline!important;white-space:nowrap!important;line-height:1!important}'
            .'.invoice-sar-symbol__svg{display:inline-block!important;width:0.95em!important;height:1em!important;vertical-align:middle!important;margin:0!important}'
            .'.invoice-sar-symbol__fallback{display:none!important}'
            .'.invoice-print-money{display:inline!important;white-space:nowrap!important}'
            .'.invoice-print-totals__ar{white-space:normal!important;overflow-wrap:anywhere!important;font-size:8.5pt!important}'
            .'.invoice-print-totals__en{font-size:8.5pt!important}'
            .'.invoice-print-totals__value{white-space:nowrap!important;direction:ltr!important;text-align:end!important;font-size:9.5pt!important}'
            .'.invoice-print-totals td{padding:4px 5px!important}'
            .'.print-tpl-modern__totals-net td{border-top:1px solid #1a1a1a!important;padding-top:5px!important}'
            .'.invoice-print-root--pdf .invoice-a4__header{display:table!important;width:100%!important;table-layout:fixed;border-bottom:1px solid #1a1a1a;margin-bottom:5px;padding-bottom:4px}'
            .'.invoice-print-root--pdf .invoice-a4__header>div{display:table-cell!important;vertical-align:top;width:33%}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side{font-size:9pt!important;line-height:1.35!important;margin:0!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side p{margin:0!important;padding:0!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side--ar{text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__title{margin:4px 0;font-size:14pt!important;font-weight:700}'
            .'.invoice-print-root--pdf .print-tpl-modern__rule{margin:0 0 5px!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table{margin-bottom:5px;font-size:8.5pt}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table th,.invoice-print-root--pdf .invoice-a4__buyer-table td{padding:4px 5px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines{font-size:9pt;margin-bottom:5px}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines th,.invoice-print-root--pdf .print-tpl-modern__lines td{padding:4px 5px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead th .ar{font-size:8pt!important}'
            .'.invoice-print-root--pdf td.tabular-nums{white-space:nowrap!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__words{margin-top:4px;font-size:8.5pt;line-height:1.35;page-break-inside:avoid}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing{margin-top:5px;gap:8px 12px}'
            .'.invoice-print-root--pdf .print-tpl-modern__sheet-footer,.invoice-print-root--pdf .invoice-a4__footer.print-only{display:none!important}'
            .'.print-tpl-modern__closing{display:flex!important;flex-wrap:nowrap;justify-content:space-between;align-items:flex-start;page-break-inside:avoid;break-inside:avoid;margin-top:5px}'
            .'.invoice-print-totals{display:table!important;width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.invoice-print-totals tr{display:table-row!important}'
            .'.invoice-print-totals td{display:table-cell!important;vertical-align:middle!important}'
            .'.print-tpl-modern__totals{display:table!important;min-width:240px;max-width:360px;margin-left:auto;font-size:9pt!important}'
            .'.print-classic__totals-wrap{display:flex;justify-content:flex-end}'
            .'.print-classic__totals{display:table!important;min-width:62%;margin-left:auto}'
            .'.invoice-print-words-block{display:table!important;width:100%!important;table-layout:fixed!important}'
            .'.invoice-print-words-block__en,.invoice-print-words-block__ar{display:table-cell!important;width:50%!important;vertical-align:top!important;padding:0 4px!important}'
            .'.invoice-print-words-block__en{text-align:start!important}'
            .'.invoice-print-words-block__ar{text-align:end!important;direction:rtl!important}'
            .'.invoice-a4__qr-img,.print-tpl-modern__qr-wrap img,.print-classic__top-right img.invoice-a4__qr-img{width:88px;height:88px}'
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

    private static function shouldSkipArabicShaping(?\DOMNode $node): bool
    {
        while ($node instanceof \DOMElement) {
            $class = (string) $node->getAttribute('class');
            if (str_contains($class, 'invoice-pdf-ar-plaintext') || str_contains($class, 'invoice-pdf-ltr-digits')) {
                return true;
            }

            $node = $node->parentNode;
        }

        return false;
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

            if (self::shouldSkipArabicShaping($parent)) {
                continue;
            }

            $text = $node->nodeValue ?? '';
            if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
                continue;
            }

            if (preg_match('/\d/u', $text)) {
                continue;
            }

            $node->nodeValue = $arabic->utf8Glyphs($text, max(80, mb_strlen($text)), false, false);
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

    private static function inlineSarSymbolsForDompdf(string $html): string
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