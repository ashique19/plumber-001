<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceDocumentType;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Support\EnabledLocales;
use App\Support\Invoicing\InvoicePrintPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): View|RedirectResponse
    {
        abort_unless($invoice->isIssued(), 404);

        $business = BusinessSetting::singleton();
        $locale = EnabledLocales::resolve($request->query('locale', app()->getLocale()));
        app()->setLocale($locale);

        $layout = (string) $request->query('layout', '');
        if ($layout === '') {
            $layout = $invoice->document_type === InvoiceDocumentType::Standard ? 'a4' : 'cash';
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

        $baseParams = array_filter([
            'locale' => $locale !== config('locales.default') ? $locale : null,
        ]);

        $printRoute = fn (string $l) => route('admin.invoices.print', array_merge([$invoice], $baseParams, ['layout' => $l]));

        if ($layout === 'receipt') {
            if ($order === null) {
                $layout = 'cash';
            } else {
                return redirect()->route('admin.pos.receipt', array_merge([$order], $baseParams, ['layout' => 'receipt']));
            }
        }

        $receiptUrl = $order !== null
            ? route('admin.pos.receipt', array_merge([$order], $baseParams, ['layout' => 'receipt']))
            : null;

        $viewName = $layout === 'cash' ? 'admin.invoices.print-cash' : 'admin.invoices.print-a4';

        return view($viewName, [
            't' => $t,
            'receiptUrl' => $receiptUrl,
            'cashUrl' => $layout !== 'cash' ? $printRoute('cash') : null,
            'a4Url' => $layout !== 'a4' ? $printRoute('a4') : null,
        ]);
    }
}