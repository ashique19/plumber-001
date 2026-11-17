<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        if (! $invoice->isIssued()) {
            return redirect()
                ->back()
                ->with('error', __('invoices.pdf_not_available'));
        }

        if (! HubInvoiceRowActions::canDownloadOrPrint($invoice)) {
            return redirect()
                ->back()
                ->with('error', __('invoices.pdf_not_cleared'));
        }

        $sheet = PrintSheetFactory::invoice($invoice, $request, forPdf: true);
        $html = view($sheet['view'], $sheet['data'])->render();

        return DocumentPdfRenderer::download($html, $invoice->document_number.'.pdf');
    }
}