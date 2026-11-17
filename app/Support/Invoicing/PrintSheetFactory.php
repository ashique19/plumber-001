<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Quotation;
use App\Support\EnabledLocales;
use Illuminate\Http\Request;

final class PrintSheetFactory
{
    /**
     * @return array{view: string, data: array<string, mixed>}
     */
    public static function invoice(Invoice $invoice, Request $request, bool $forPdf = false): array
    {
        $business = BusinessSetting::singleton();
        $locale = PrintBodyLocale::resolveFromRequest($request->query('locale'));
        app()->setLocale($locale);

        $isStandardDocument = $invoice->document_type === InvoiceDocumentType::Standard;
        $layout = (string) $request->query('layout', '');
        if ($layout === '' || $forPdf) {
            $layout = 'a4';
        }
        if (! in_array($layout, ['a4', 'cash', 'receipt'], true)) {
            $layout = $isStandardDocument ? 'a4' : 'cash';
        }

        $branch = $invoice->branch;
        $order = null;

        if ($invoice->source_type === PosOrder::class && $invoice->source_id !== null) {
            $order = PosOrder::query()
                ->with(['branch', 'cashier', 'customer'])
                ->findOrFail($invoice->source_id);
            $t = InvoicePrintPresenter::fromPosOrder($business, $order, $invoice, $order->branch ?? $branch);
        } else {
            $t = InvoicePrintPresenter::fromInvoice($business, $invoice, $branch);
        }

        if ($forPdf) {
            $t['qr_png'] = PrintQrPng::dataUri($t['qr_tlv_base64'] ?? null);
        }

        $template = PrintTemplateResolver::forInvoice($business, $request)->value;
        $view = $layout === 'cash' && ! $forPdf
            ? 'admin.invoices.print-cash'
            : "print.invoices.{$template}";

        return [
            'view' => $view,
            'data' => [
                't' => $t,
                'receiptUrl' => null,
                'cashUrl' => null,
                'a4Url' => null,
                'zatcaNotSent' => ZatcaSubmissionStatus::invoiceNotSentToZatca($invoice),
                'isPdf' => $forPdf,
            ],
        ];
    }

    /**
     * @return array{view: string, data: array<string, mixed>}
     */
    public static function quotation(Quotation $quotation, Request $request, bool $forPdf = false): array
    {
        $business = BusinessSetting::singleton();
        $locale = PrintBodyLocale::resolveFromRequest($request->query('locale'));
        app()->setLocale($locale);

        $t = QuotationPrintPresenter::fromQuotation($business, $quotation, $quotation->branch);
        $template = PrintTemplateResolver::forQuotation($business, $request)->value;

        return [
            'view' => "print.invoices.{$template}",
            'data' => [
                't' => $t,
                'receiptUrl' => null,
                'cashUrl' => null,
                'a4Url' => null,
                'zatcaNotSent' => false,
                'isPdf' => $forPdf,
            ],
        ];
    }
}
