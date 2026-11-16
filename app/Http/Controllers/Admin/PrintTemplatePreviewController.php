<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrintTemplatePreviewController extends Controller
{
    public function __invoke(Request $request, string $kind, string $template): RedirectResponse
    {
        abort_unless(in_array($kind, ['invoice', 'quotation'], true), 404);
        abort_unless(in_array($template, ['modern', 'classic'], true), 404);

        if ($kind === 'invoice') {
            $invoice = Invoice::query()
                ->where('status', InvoiceStatus::Issued)
                ->latest('issued_at')
                ->first();

            abort_if($invoice === null, 404, 'No issued invoice available for preview.');

            return redirect()->route('admin.invoices.print', array_filter([
                'invoice' => $invoice,
                'layout' => 'a4',
                'template' => $template,
                'locale' => $request->query('locale'),
            ]));
        }

        $quotation = Quotation::query()->latest()->first();
        abort_if($quotation === null, 404, 'No quotation available for preview.');

        return redirect()->route('admin.quotations.print', array_filter([
            'quotation' => $quotation,
            'template' => $template,
            'locale' => $request->query('locale'),
        ]));
    }
}