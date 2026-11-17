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

    public static function wrapHtml(string $html): string
    {
        $fontPath = resource_path('fonts/Amiri-Regular.ttf');
        $css = self::printCss();
        $fontFace = is_file($fontPath)
            ? '@font-face{font-family:AmiriPdf;src:url(\'file://'.$fontPath.'\') format(\'truetype\');font-weight:normal;font-style:normal;}'
            : '';
        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$fontFace
            .$css
            .self::pdfOverlayCss()
            .'</style></head><body>'
            .self::shapeArabic($html)
            .'</body></html>';

        return self::localizeAssetUrls($wrapped);
    }

    private static function amiriAvailable(): bool
    {
        return is_file(resource_path('fonts/Amiri-Regular.ttf'));
    }

    private static function pdfOverlayCss(): string
    {
        return '.invoice-print-toolbar,.no-print{display:none!important}'
            .'html,body,.invoice-print-root,.invoice-print-root *{font-family:AmiriPdf,DejaVu Sans,sans-serif!important}'
            .'.invoice-print-root{padding:4mm;font-size:9pt;max-width:none}'
            .'.invoice-print-root [dir=rtl],.invoice-print-root .ar,.invoice-a4__title-ar,.invoice-a4__desc-ar,.invoice-a4__footer-ar,.invoice-a4__seller-side--ar,.invoice-print-label__ar,.print-tpl-modern__totals-ar,.print-classic__title-ar,.classic-party__name-ar,.print-classic__words{direction:rtl;text-align:right;unicode-bidi:isolate}'
            .'.invoice-sar-symbol__svg{display:none!important}'
            .'.invoice-sar-symbol__fallback{display:inline!important;font-size:.85em;font-weight:700}'
            .'.print-tpl-modern__closing{page-break-inside:avoid;break-inside:avoid;margin-top:6px;grid-template-columns:110px 1fr;gap:8px 12px}'
            .'.invoice-a4__qr-img,.print-tpl-modern__qr-wrap img,.print-classic__top-right img.invoice-a4__qr-img{width:96px;height:96px}'
            .'.invoice-a4__qr canvas{display:none!important}'
            .'.print-tpl-modern,.print-tpl-classic{padding-bottom:8mm}'
            .'.print-tpl-modern .invoice-a4__footer.print-tpl-modern__sheet-footer{position:relative;inset:auto;padding:4px 0 0;background:transparent}'
            .'.invoice-a4__logo,.print-classic__logo{max-height:64px}';
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
            $text = $node->nodeValue ?? '';
            if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
                continue;
            }
            $node->nodeValue = $arabic->utf8Glyphs($text, max(80, mb_strlen($text)), false, false);
        }

        $out = $dom->saveHTML();

        return is_string($out) ? $out : $html;
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
