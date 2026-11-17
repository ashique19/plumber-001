<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->isIssued() && HubInvoiceRowActions::canDownloadOrPrint($invoice), 404);

        $sheet = PrintSheetFactory::invoice($invoice, $request, forPdf: true);
        $html = view($sheet['view'], $sheet['data'])->render();

        return DocumentPdfRenderer::download($html, $invoice->document_number.'.pdf');
    }
}
