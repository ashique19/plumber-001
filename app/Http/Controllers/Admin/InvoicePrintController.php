<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Support\EnabledLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePrintController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->source_type === PosOrder::class && $invoice->source_id !== null) {
            $order = PosOrder::query()->findOrFail($invoice->source_id);

            $locale = EnabledLocales::resolve($request->query('locale', app()->getLocale()));

            return redirect()->route('admin.pos.receipt', [
                'order' => $order,
                'locale' => $locale,
            ]);
        }

        abort(404, 'Print view is only available for POS-originated invoices.');
    }
}
