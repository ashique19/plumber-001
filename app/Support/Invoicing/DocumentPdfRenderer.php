<?php

namespace App\Support\Invoicing;

use ArPHP\I18N\Arabic;
use DOMDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use DOMXPath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class DocumentPdfRenderer
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function download(string $html, string $filename, array $context = []): Response
    {
        $binary = self::render($html, $context);
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

    /**
     * @param  array<string, mixed>  $context
     */
    public static function render(string $html, array $context = []): string
    {
        $wrapped = self::wrapHtml($html);

        try {
            return self::renderWithDompdf($wrapped);
        } catch (\Throwable $dompdfError) {
            Log::error('document_pdf_render_failed', array_merge($context, [
                'dompdf_message' => $dompdfError->getMessage(),
            ]));

            throw $dompdfError;
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

    public static function wrapHtml(string $html): string
    {
        $css = self::printCss();
        $body = self::shapeArabic(
            DocumentPdfArabicLineWrap::apply(
                self::inlineSarSymbolsForPdf($html),
            ),
        );

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .self::pdfFontFaceCss()
            .$css
            .self::pdfOverlayCss()
            .'</style></head><body>'
            .$body
            .'</body></html>';

        return self::localizeAssetUrls($wrapped);
    }

    private static function renderWithDompdf(string $wrapped): string
    {
        DocumentPdfFonts::assertReady();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', DocumentPdfFonts::FAMILY_LATIN);
        $options->setChroot(base_path());
        DocumentPdfFonts::applyTo($options);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->setCallbacks([
            [
                'event' => 'end_document',
                'f' => DocumentPdfPageFooter::endDocumentCallback(),
            ],
        ]);
        $dompdf->loadHtml($wrapped, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return list<string>
     */
    public static function pdfProducerLine(string $binary): ?string
    {
        $path = storage_path('app/pdf-producer-probe-'.uniqid().'.pdf');
        file_put_contents($path, $binary);
        $info = trim((string) shell_exec('pdfinfo '.escapeshellarg($path).' 2>/dev/null'));
        @unlink($path);

        if ($info === '' || ! preg_match('/^Producer:\s*(.+)$/m', $info, $m)) {
            return preg_match('/dompdf/i', $binary) ? 'dompdf' : null;
        }

        return trim($m[1]);
    }

    /**
     * @return list<string>
     */
    public static function embeddedPdfFontNames(string $binary): array
    {
        $path = storage_path('app/pdf-font-probe-'.uniqid().'.pdf');
        file_put_contents($path, $binary);
        $raw = trim((string) shell_exec('pdffonts '.escapeshellarg($path).' 2>/dev/null'));
        @unlink($path);

        if ($raw === '') {
            return [];
        }

        $names = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'name') || str_starts_with($line, '----')) {
                continue;
            }
            if (! preg_match('/^(\S+)/', $line, $m)) {
                continue;
            }
            $full = $m[1];
            $base = str_contains($full, '+') ? substr($full, (int) strrpos($full, '+') + 1) : $full;
            $names[] = strtolower($base);
        }

        return array_values(array_unique($names));
    }

    private static function pdfFontFaceCss(): string
    {
        $regular = DocumentPdfFonts::storageDir().'/Amiri-Regular.ttf';
        $bold = DocumentPdfFonts::storageDir().'/Amiri-Bold.ttf';
        if (! is_file($regular)) {
            return '';
        }

        $rules = '@font-face{font-family:'.DocumentPdfFonts::FAMILY_ARABIC.';src:url(\'file://'.$regular.'\') format(\'truetype\');font-weight:normal;font-style:normal}';
        if (is_file($bold)) {
            $rules .= '@font-face{font-family:'.DocumentPdfFonts::FAMILY_ARABIC.';src:url(\'file://'.$bold.'\') format(\'truetype\');font-weight:bold;font-style:normal}';
        }

        return $rules;
    }

    private static function pdfOverlayCss(): string
    {
        $latin = DocumentPdfFonts::FAMILY_LATIN.',sans-serif';
        $arFont = is_file(DocumentPdfFonts::storageDir().'/Amiri-Regular.ttf')
            ? DocumentPdfFonts::FAMILY_ARABIC.','.$latin
            : $latin;

        $arSelectors = '.invoice-print-root .invoice-print-ar,.invoice-print-root .ar,.invoice-a4__title-ar,.invoice-a4__desc-ar,.invoice-a4__footer-ar,.invoice-a4__seller-side--ar,.invoice-print-label__ar,.invoice-print-labeled-row__ar,.invoice-a4__buyer-name-ar,.invoice-a4__table th .ar,.invoice-print-th-label__ar,.invoice-print-info-grid__ar,.print-classic__title-ar,.print-classic__cell-label.ar,.classic-party__name-ar,.print-classic__lines th .ar';
        $arBlocks = '.invoice-pdf-ar-block,.invoice-national-address-ar--pdf,.invoice-a4__qr-note-ar,.invoice-a4__qr-note-ar__line,.invoice-print-words-block__ar';

        return '.invoice-print-toolbar,.no-print,.invoice-zatca-notice{display:none!important}'
            .'html,body,.invoice-print-root{font-family:'.$latin.'!important;font-size:9pt;line-height:1.35;color:#111}'
            .'.invoice-print-root :not([class*="invoice-print-ar"]):not(.ar):not([dir=rtl]):not([class*="invoice-pdf-ar"]){font-family:'.$latin.'!important}'
            .$arSelectors.'{font-family:'.$arFont.'!important;direction:rtl!important;text-align:right!important;unicode-bidi:embed!important}'
            .$arBlocks.'{font-family:'.$arFont.'!important;direction:rtl!important;text-align:right!important;unicode-bidi:normal!important;display:block!important}'
            .'.invoice-pdf-ltr-digits{direction:ltr!important;unicode-bidi:embed!important;font-family:'.$latin.'!important}'
            .'.invoice-a4__title-bn,.print-classic__title-bn{display:none!important}'
            .'.invoice-print-root{padding:2mm 6mm;font-size:9pt;max-width:none}'
            .'.invoice-print-root--pdf{padding:2mm 6mm 2mm!important}'
            .'.invoice-sar-symbol{display:inline!important;white-space:nowrap!important;line-height:1!important}'
            .'.invoice-sar-symbol__svg{display:inline-block!important;width:0.95em!important;height:1em!important;vertical-align:middle!important;margin:0!important}'
            .'.invoice-sar-symbol__fallback{display:none!important}'
            .'.invoice-print-money{display:inline!important;white-space:nowrap!important}'
            .'.invoice-print-totals,.print-tpl-modern__totals{font-family:'.$latin.'!important;font-weight:normal!important;font-size:9pt!important}'
            .'.invoice-print-root--pdf .invoice-print-totals__en{font-family:'.$latin.'!important;font-weight:normal!important;font-size:8pt!important;color:#666!important}'
            .'.invoice-print-root--pdf .invoice-print-totals__ar{font-family:'.$arFont.'!important;font-weight:normal!important;font-size:8pt!important;color:#111!important;white-space:nowrap!important;overflow-wrap:normal!important;text-align:right!important;width:40%!important;padding-left:8px!important}'
            .'.invoice-print-totals__ar{text-align:right!important}'
            .'.invoice-print-totals__en{text-align:right!important;width:30%!important;padding-right:8px!important}'
            .'.invoice-print-totals__value{white-space:nowrap!important;direction:ltr!important;text-align:right!important;font-size:9pt!important;font-family:'.$latin.'!important;width:30%!important}'
            .'.invoice-print-totals td{padding:3px 4px!important;vertical-align:middle!important;font-family:inherit!important;font-weight:normal!important}'
            .'.invoice-print-totals strong{font-family:inherit!important;font-weight:bold!important}'
            .'.invoice-print-totals__row--strong td,.invoice-print-totals__row--strong .invoice-print-totals__en,.invoice-print-totals__row--strong .invoice-print-totals__value{font-weight:bold!important;font-family:'.$latin.'!important}'
            .'.invoice-print-totals__row--strong .invoice-print-totals__ar{font-weight:bold!important;font-family:'.$arFont.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-label{font-family:'.$latin.'!important;font-weight:normal!important;color:#666!important;font-size:8pt!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-label.ar{font-family:'.$arFont.'!important;font-weight:normal!important;color:#666!important;font-size:8pt!important;text-align:right!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.print-tpl-modern__totals-net td{border-top:1px solid #1a1a1a!important;padding-top:5px!important}'
            .'.invoice-print-root--pdf .invoice-a4__header{border-bottom:1px solid #1a1a1a;margin-bottom:3px;padding-bottom:2px}'
            .'.invoice-print-root--pdf .invoice-a4__header-table{width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-cell--en{width:38%!important;padding:0 3px!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-cell--logo{width:24%!important;text-align:center!important;padding:0 3px!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-cell--ar{width:38%!important;padding:0 3px!important;vertical-align:top!important;text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-row--ids td{padding-top:1px!important;line-height:1.2!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-row--ids .invoice-print-seller-header-ids__line{margin:0!important;padding:0!important;line-height:1.2!important;font-size:8pt!important;color:#666!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-row--ids .invoice-print-seller-header-ids__label{font-size:8pt!important;line-height:1.2!important;white-space:nowrap!important;font-weight:normal!important;color:#666!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-row--ids .invoice-a4__header-cell--ar .invoice-print-seller-header-ids__label{font-family:'.$arFont.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__header-row--ids .invoice-print-seller-header-ids__value{font-size:9pt!important;line-height:1.2!important;color:#111!important}'
            .'.invoice-print-root--pdf .invoice-pdf-ar-preline--compact,.invoice-print-root--pdf .invoice-pdf-ar-lines--compact{font-size:7pt!important;line-height:1.2!important}'
            .'.invoice-national-address-ar__line,.invoice-pdf-ar-address-line{display:block!important;margin:0!important;line-height:1.3!important;text-align:right!important;direction:rtl!important;font-family:'.$arFont.'!important;white-space:nowrap!important}'
            .'.invoice-pdf-sans-label{font-family:dejavusans!important;font-style:normal!important;font-weight:normal!important;font-size:8pt!important;color:#666!important}'
            .'.invoice-pdf-sans-value{font-family:dejavusans!important;font-style:normal!important;font-weight:normal!important;font-size:9pt!important;color:#111!important}'
            .'.invoice-a4__buyer-name-cell{display:block!important;width:100%!important;max-width:100%!important}'
            .'.invoice-a4__buyer-name-en{display:block!important;text-align:left!important;font-family:dejavusans!important;font-size:9pt!important;line-height:1.3!important;margin:0!important;color:#111!important}'
            .'.invoice-a4__buyer-name-ar{display:block!important;text-align:right!important;font-family:'.$arFont.'!important;font-weight:600!important;font-size:9pt!important;line-height:1.35!important;margin:2px 0 0!important}'
            .'.invoice-a4__buyer-address-cell{display:block!important;width:100%!important;max-width:100%!important}'
            .'.invoice-a4__buyer-address-en{display:block!important;text-align:left!important;font-family:dejavusans!important;max-width:100%!important}'
            .'.invoice-a4__buyer-address-en__line{display:block!important;white-space:normal!important;line-height:1.3!important;margin:0!important;font-family:dejavusans!important;font-size:9pt!important;color:#111!important;overflow-wrap:normal!important;word-break:normal!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-value{overflow:hidden!important;max-width:56%!important;word-wrap:break-word!important;font-family:dejavusans!important;font-size:9pt!important;color:#111!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-value *:not(.invoice-print-ar):not(.ar):not([dir=rtl]){font-family:dejavusans!important;color:#111!important;line-height:1.3!important}'
            .'.invoice-print-root--pdf .invoice-print-info-grid__value,.invoice-print-root--pdf .invoice-print-info-grid__value *{font-family:dejavusans!important;color:#111!important}'
            .'.invoice-a4__buyer-table-label .invoice-pdf-sans-label{font-family:dejavusans!important;font-weight:normal!important;font-style:normal!important;color:#666!important;font-size:8pt!important}'
            .'.invoice-print-info-grid__en .invoice-pdf-sans-label{font-family:dejavusans!important;font-weight:normal!important;font-style:normal!important;color:#666!important;font-size:8pt!important}'
            .'.invoice-print-root--pdf .invoice-a4__logo{max-width:46mm!important;max-height:70px!important;margin:0 auto!important;display:block!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side{font-size:8pt!important;line-height:1.3!important;margin:0!important;font-family:'.$latin.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side--en{word-wrap:normal!important;overflow-wrap:normal!important;word-break:normal!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-name-en{font-family:'.$latin.'!important;font-weight:700!important;font-size:8pt!important;line-height:1.3!important;display:block!important;white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-address-en__line{display:block!important;margin:0!important;line-height:1.3!important;font-size:7.5pt!important;font-family:'.$latin.'!important;white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}'
            .'.invoice-print-seller-header-ids{margin-top:2px!important;line-height:1.3!important;color:#666!important;font-family:'.$latin.'!important;font-size:8pt!important}'
            .'.invoice-print-seller-header-ids__label{font-family:'.$latin.'!important;font-size:8pt!important;font-weight:normal!important;color:#666!important}'
            .'.invoice-print-seller-header-ids__value{color:#111!important;font-family:'.$latin.'!important;font-size:9pt!important;font-weight:normal!important}'
            .'.invoice-print-seller-header-ids--ar,.invoice-print-seller-header-ids--ar .invoice-print-seller-header-ids__label{font-family:'.$arFont.'!important;text-align:right!important}'
            .'.invoice-national-address-ar--pdf{max-width:58mm!important;margin-left:auto!important;text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side p{margin:0!important;padding:0!important}'
            .'.invoice-print-root--pdf .invoice-a4__seller-side--ar{text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__title{margin:2px 0;font-size:13pt!important;font-weight:700}'
            .'.invoice-print-root--pdf .print-tpl-modern__rule{margin:0 0 3px!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table{width:100%!important;margin-bottom:3px;font-size:8.5pt!important;font-family:'.$latin.'!important;border-collapse:collapse!important;table-layout:fixed!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table th,.invoice-print-root--pdf .invoice-a4__buyer-table td{padding:2px 4px!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table tbody tr{border-bottom:1px solid #ccc!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-label{width:22%!important;font-family:'.$latin.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-value{width:56%!important;text-align:left!important;font-family:'.$latin.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-label.ar{width:22%!important;font-family:'.$arFont.'!important;text-align:right!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split{display:table!important;width:100%!important;table-layout:fixed!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-col{display:table-cell!important;width:50%!important;float:none!important;padding:0!important;vertical-align:top!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row{display:table!important;width:100%!important;table-layout:fixed!important;margin:0!important;border-bottom:1px solid #ccc!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label{display:table-cell!important;width:24%!important;vertical-align:middle!important;font-family:'.$latin.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-value{display:table-cell!important;width:48%!important;text-align:left!important;vertical-align:middle!important;font-family:'.$latin.'!important}'
            .'.invoice-print-root--pdf .invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label.ar{display:table-cell!important;width:28%!important;text-align:right!important;vertical-align:middle!important;font-family:'.$arFont.'!important}'
            .'.invoice-a4__buyer-table-value--nowrap{white-space:nowrap!important;overflow:visible!important;overflow-wrap:normal!important;word-break:normal!important;font-size:9pt!important;text-align:left!important;font-family:dejavusans!important;color:#111!important}'
            .'.invoice-a4__buyer-table-split-row .invoice-a4__buyer-table-label{white-space:nowrap!important;font-size:8pt!important}'
            .'.invoice-a4__buyer-table-split-row{grid-template-columns:24% 1fr 26%!important;align-items:center!important}'
            .'.print-tpl-modern__sheet-footer{display:grid!important;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;align-items:end!important;gap:8px 12px!important;width:100%!important}'
            .'.print-tpl-modern__footer-name-en{text-align:left!important}'
            .'.print-tpl-modern__footer-name-ar{text-align:right!important;direction:rtl!important}'
            .'.print-tpl-modern__footer-page{text-align:center!important;margin:0!important;white-space:nowrap!important}'
            .'.print-tpl-modern__words{align-items:flex-start!important}'
            .'.print-tpl-modern__vat-pct{text-align:right!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines{font-size:8.5pt;margin-bottom:3px;width:100%!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines th,.invoice-print-root--pdf .print-tpl-modern__lines td{padding:2px 4px!important;line-height:1.3!important;vertical-align:middle!important;font-family:'.$latin.'!important;color:#111!important}'
            .'.invoice-print-root--pdf .invoice-a4__desc-en,.invoice-print-root--pdf .invoice-a4__desc-en *{font-family:'.$latin.'!important;color:#111!important}'
            .'.invoice-print-root--pdf .invoice-print-th-label__en{font-family:'.$latin.'!important;color:#666!important;font-size:8pt!important;font-weight:normal!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead th{font-size:7.5pt!important;line-height:1.2!important;vertical-align:middle!important;padding:2px 3px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead th .invoice-print-th-label__en{font-size:7pt!important;white-space:nowrap!important;display:block!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead th .ar{font-size:7pt!important;display:block!important;margin-top:1px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines th.col-taxable,.invoice-print-root--pdf .print-tpl-modern__lines th.col-vat{white-space:nowrap!important;font-size:7pt!important}'
            .'.invoice-print-labeled-row{display:grid!important;grid-template-columns:22% 1fr 24%!important;align-items:center!important;width:100%!important;gap:4px 6px!important}'
            .'.invoice-print-labeled-row__en{text-align:left!important;font-family:'.$latin.'!important;font-weight:600!important}'
            .'.invoice-print-labeled-row__value{text-align:left!important;font-family:'.$latin.'!important}'
            .'.invoice-print-labeled-row__ar{text-align:right!important}'
            .'.invoice-print-info-grid,.invoice-print-meta-grid{width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;margin-bottom:3px!important;font-size:9pt!important;line-height:1.3!important}'
            .'.invoice-print-info-grid>tbody>tr>td{padding:0 4px!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.invoice-print-info-grid__pair{padding:0!important;vertical-align:middle!important}'
            .'.invoice-print-info-grid-field{width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.invoice-print-info-grid-field td{padding:2px 4px!important;vertical-align:middle!important;overflow:visible!important;line-height:1.3!important}'
            .'.invoice-print-info-grid__en{text-align:left!important;direction:ltr!important;unicode-bidi:embed!important;font-weight:normal!important;font-size:8pt!important;font-family:dejavusans!important;color:#666!important;width:30%!important;min-width:0!important;max-width:30%!important;padding:2px 4px!important;white-space:normal!important;overflow-wrap:break-word!important;word-break:normal!important;line-height:1.25!important;vertical-align:middle!important;overflow:hidden!important}'
            .'.invoice-print-info-grid__ar{text-align:right!important;font-size:8pt!important;font-weight:normal!important;color:#666!important;font-family:'.$arFont.'!important;width:28%!important;min-width:0!important;max-width:28%!important;padding:2px 4px!important;white-space:normal!important;overflow-wrap:break-word!important;word-break:normal!important;line-height:1.25!important;overflow:hidden!important;vertical-align:middle!important}'
            .'.invoice-print-info-grid__col-en{width:30%!important}'
            .'.invoice-print-info-grid__col-value{width:42%!important}'
            .'.invoice-print-info-grid__col-ar{width:28%!important}'
            .'.invoice-print-info-grid__value{text-align:left!important;direction:ltr!important;unicode-bidi:embed!important;font-family:'.$latin.'!important;font-size:9pt!important;color:#111!important;width:42%!important;padding:2px 4px!important;overflow-wrap:normal!important;word-break:normal!important;vertical-align:middle!important;line-height:1.3!important}'
            .'.invoice-pdf-ar-address-line{margin:0!important;line-height:1.35!important;text-align:right!important}'
            .'.invoice-print-info-grid__value--nowrap{white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}'
            .'.invoice-print-info-grid__label--nowrap{white-space:normal!important;overflow-wrap:break-word!important;font-size:8pt!important;line-height:1.25!important}'
            .'.invoice-national-address-ar--pdf{margin:0!important;line-height:1.45!important}'
            .'.invoice-a4__table--product-lines th.col-index,.invoice-a4__table--product-lines td.col-index{text-align:center!important}'
            .'.invoice-a4__table--product-lines th.col-num,.invoice-a4__table--product-lines td.col-num{text-align:right!important}'
            .'.invoice-a4__table--product-lines th.col-desc,.invoice-a4__table--product-lines td.col-desc{text-align:left!important}'
            .'.invoice-print-root--pdf td.tabular-nums{white-space:nowrap!important}'
            .'.invoice-print-words-block__en,.invoice-print-words-block__ar,.invoice-a4__words{font-family:'.$latin.'!important;font-size:8pt!important;line-height:1.25!important}'
            .'.invoice-print-root--pdf .invoice-print-words-block__en{font-size:7pt!important;white-space:normal!important;overflow-wrap:break-word!important;word-wrap:break-word!important;color:#111!important;margin:0!important;max-width:100%!important;box-sizing:border-box!important}'
            .'.invoice-print-root--pdf .invoice-pdf-ar-preline{display:block!important;white-space:nowrap!important;overflow-wrap:normal!important;line-height:1.3!important;text-align:right!important;direction:rtl!important}'
            .'.invoice-print-root--pdf .invoice-a4__desc-ar .invoice-pdf-ar-preline{font-size:8pt!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines thead{display:table-header-group!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__lines tbody tr{page-break-inside:avoid!important;break-inside:avoid!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing,.invoice-print-root--pdf .print-tpl-modern__closing-grid,.invoice-print-root--pdf .print-tpl-modern__closing-main{page-break-inside:avoid!important;break-inside:avoid!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing .print-tpl-modern__words{max-width:100%!important;width:100%!important}'
            .'.invoice-print-words-block__ar{font-family:'.$arFont.'!important}'
            .'.print-tpl-modern__closing-grid{width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.print-tpl-modern__closing-qr{width:48mm!important;max-width:48mm!important;vertical-align:top!important;padding-right:4px!important}'
            .'.print-tpl-modern__closing-main{width:62%!important;vertical-align:top!important;text-align:right!important}'
            .'.print-tpl-modern__closing--no-qr .print-tpl-modern__closing-main{width:100%!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing .print-tpl-modern__words{margin-top:3px!important;margin-left:auto!important;margin-bottom:3px!important;width:100%!important;max-width:26rem!important;min-width:0!important;border:1px solid #1a1a1a!important;padding:4px 6px!important;box-sizing:border-box!important;font-size:7.5pt;line-height:1.25;page-break-inside:avoid;display:block!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__qr-wrap{width:100%!important;max-width:52mm!important;margin:0!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__qr-wrap .invoice-a4__qr{text-align:center!important;margin:0 auto!important;max-width:52mm!important}'
            .'.print-tpl-modern__closing .invoice-a4__qr-note-lines{width:100%!important;max-width:52mm!important;margin:4px auto 0!important}'
            .'.print-tpl-modern__closing .invoice-a4__qr-note-en{margin:0!important;font-size:7.5pt!important;line-height:1.25!important;font-family:'.$latin.'!important;text-align:center!important;direction:ltr!important;white-space:nowrap!important}'
            .'.print-tpl-modern__closing .invoice-a4__qr-note-ar__line{margin:0!important;font-size:7.5pt!important;line-height:1.25!important;text-align:center!important;direction:rtl!important;unicode-bidi:embed!important;white-space:nowrap!important}'
            .'.print-tpl-modern__closing .invoice-a4__qr-note-ar-lines .invoice-a4__qr-note-ar__line + .invoice-a4__qr-note-ar__line{margin-top:2px!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__sheet-footer,.invoice-print-root--pdf .invoice-a4__footer{display:none!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing{width:100%!important;margin-top:4px!important;overflow:visible!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__closing-grid td{display:table-cell!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__totals{page-break-after:avoid!important;float:none!important;width:100%!important;max-width:26rem!important;margin:0 0 0 auto!important;display:table!important;border-collapse:collapse!important}'
            .'.invoice-print-root--pdf .print-tpl-modern__qr-wrap--empty{display:none!important}'
            .'.invoice-print-totals{width:100%!important;table-layout:fixed!important;border-collapse:collapse!important}'
            .'.print-tpl-modern__totals{min-width:0;max-width:26rem;font-size:9pt!important}'
            .'.print-tpl-modern__vat-cell{white-space:nowrap!important}'
            .'.print-tpl-modern__vat-pct{display:block!important;font-size:7pt!important;line-height:1.15!important;text-align:end!important}'
            .'.invoice-a4__qr-note{font-size:7pt!important;line-height:1.35!important;margin-top:4px!important}'
            .'.invoice-a4__qr canvas{display:none!important}'
            .'.print-classic__totals-wrap{display:flex;justify-content:flex-end}'
            .'.print-classic__totals{display:table!important;min-width:62%;margin-left:auto}'
            .'.print-classic__closing{display:table!important;width:100%!important}'
            .'.invoice-print-words-block{display:flex!important;align-items:flex-start!important;flex-wrap:nowrap!important;gap:8px 12px!important}'
            .'.invoice-print-words-block__en,.invoice-print-words-block__ar{display:block!important;flex:1 1 0!important;min-width:0!important;margin:0!important}'
            .'.invoice-a4__qr-img,.print-tpl-modern__qr-wrap img,.print-classic__top-right img.invoice-a4__qr-img{width:113px!important;height:113px!important;max-width:30mm!important;max-height:30mm!important;display:block!important;margin-left:auto!important;margin-right:auto!important}'
            .'.print-tpl-modern,.print-tpl-classic{padding-bottom:1mm}'
            .'.print-tpl-modern__closing{page-break-inside:avoid!important;margin-top:4px!important}'
            .'.invoice-a4__logo,.print-classic__logo{max-height:40px}'
            .'@page{margin:6mm 9mm 12mm}';
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
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (! preg_match('/\p{Arabic}/u', $decoded)) {
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

        $src = self::sarSymbolDataUri();
        $img = '<img class="invoice-sar-symbol__svg" alt="" src="'.$src.'" style="width:1em;height:1em;vertical-align:-0.12em;display:inline-block" />';

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
