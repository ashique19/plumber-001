<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation): Response
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue') || auth('staff')->user()?->can('reports.view'), 403);

        $sheet = PrintSheetFactory::quotation($quotation, $request, forPdf: true);
        $html = view($sheet['view'], $sheet['data'])->render();

        return DocumentPdfRenderer::download($html, $quotation->quotation_number.'.pdf');
    }
}
