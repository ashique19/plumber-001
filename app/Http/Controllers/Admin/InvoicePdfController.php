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
            return $this->blockedResponse($request, __('invoices.pdf_not_available'));
        }

        if (! HubInvoiceRowActions::canDownloadOrPrint($invoice)) {
            return $this->blockedResponse($request, __('invoices.pdf_not_cleared'));
        }

        $sheet = PrintSheetFactory::invoice($invoice, $request, forPdf: true);
        $html = view($sheet['view'], $sheet['data'])->render();

        try {
            return DocumentPdfRenderer::download($html, $invoice->document_number.'.pdf', [
                'invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
            ]);
        } catch (\Throwable) {
            return redirect()
                ->back()
                ->with('error', __('invoices.pdf_render_failed'));
        }
    }

    private function blockedResponse(Request $request, string $message): Response|RedirectResponse
    {
        if ($this->expectsPdfDownload($request)) {
            abort(403, $message);
        }

        return redirect()
            ->back()
            ->with('error', $message);
    }

    private function expectsPdfDownload(Request $request): bool
    {
        if ($request->boolean('download')) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        return str_contains($accept, 'application/pdf');
    }
}