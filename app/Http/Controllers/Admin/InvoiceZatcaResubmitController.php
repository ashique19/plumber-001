<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceRowActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvoiceZatcaResubmitController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, InvoiceZatcaResubmitService $service): RedirectResponse
    {
        abort_unless($invoice->isIssued(), 404);
        abort_unless($request->user('staff')?->can('invoices.issue'), 403);
        abort_unless(HubInvoiceRowActions::canResendZatca($invoice), 403);

        $result = $service->resubmit($invoice);

        $back = (string) $request->headers->get('referer', route('admin.invoices'));
        if ($back === '' || ! str_starts_with($back, url('/'))) {
            $back = route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']);
        }

        if ($result->invoice && $result->userMessage) {
            $flashKey = match ($result->flashLevel) {
                'warning' => 'warning',
                'info' => 'info',
                'error' => 'error',
                default => $result->ok ? 'success' : 'error',
            };

            return redirect()->to($back)->with($flashKey, $result->userMessage);
        }

        if ($result->code === 'locked') {
            return redirect()->to($back)->with('warning', $result->userMessage ?? __('invoices.zatca_resubmit_locked'));
        }

        if ($result->code === 'already_success') {
            return redirect()->to($back)->with('info', $result->userMessage ?? __('invoices.zatca_resubmit_already_success'));
        }

        if ($result->userMessage) {
            return redirect()->to($back)->with('error', $result->userMessage);
        }

        return redirect()->to($back)->with('error', __('invoices.zatca_resubmit_failed'));
    }
}