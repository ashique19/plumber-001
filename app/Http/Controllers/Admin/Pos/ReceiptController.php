<?php

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\PosOrder;
use App\Support\EnabledLocales;
use App\Support\Pos\ReceiptThermalPresenter;
use App\Support\ReceiptSigningStatusLabel;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __invoke(PosOrder $order): View
    {
        if ($order->invoice_id === null) {
            abort(404, 'Invoice not issued for this order.');
        }

        $tenant = BusinessSetting::singleton();

        $order->loadMissing(['branch', 'cashier', 'customer']);
        $invoice = $order->invoice()->firstOrFail();
        $locale = EnabledLocales::resolve(request()->query('locale', app()->getLocale()));
        app()->setLocale($locale);

        return view('admin.pos.receipt', [
            'tenant' => $tenant,
            'order' => $order,
            'invoice' => $invoice,
            'locale' => $locale,
            'branch' => $order->branch,
            'compliance' => ReceiptSigningStatusLabel::forInvoice($invoice),
            'thermal' => ReceiptThermalPresenter::compose($tenant, $order, $invoice, $order->branch),
        ]);
    }
}
