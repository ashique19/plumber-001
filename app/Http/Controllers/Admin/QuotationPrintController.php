<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Support\EnabledLocales;
use App\Support\Invoicing\QuotationPrintPresenter;
use App\Support\Invoicing\PrintTemplateResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationPrintController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation): View
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue') || auth('staff')->user()?->can('reports.view'), 403);

        $business = BusinessSetting::singleton();
        $locale = EnabledLocales::resolve($request->query('locale', app()->getLocale()));
        app()->setLocale($locale);

        $branch = $quotation->branch;
        $t = QuotationPrintPresenter::fromQuotation($business, $quotation, $branch);

        $template = PrintTemplateResolver::forQuotation($business, $request)->value;

        return view("print.invoices.{$template}", [
            't' => $t,
            'receiptUrl' => null,
            'cashUrl' => null,
            'a4Url' => null,
            'zatcaNotSent' => false,
        ]);
    }
}