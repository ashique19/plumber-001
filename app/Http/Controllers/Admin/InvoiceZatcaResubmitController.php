<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Invoicing\PrintScreenBackUrl;
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

        $returnQuery = $request->input('return');
        $returnParams = is_string($returnQuery) && $returnQuery !== ''
            ? [PrintScreenBackUrl::RETURN_PARAM => $returnQuery]
            : [];

        $back = (string) $request->headers->get('referer', '');
        $printPath = parse_url(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']), PHP_URL_PATH);
        if ($back === '' || ! str_starts_with($back, url('/')) || ($printPath && ! str_contains(parse_url($back, PHP_URL_PATH) ?: '', (string) $printPath))) {
            $back = route('admin.invoices.print', array_merge(
                ['invoice' => $invoice, 'layout' => 'a4'],
                $returnParams,
            ));
        } elseif ($returnParams !== []) {
            $separator = str_contains($back, '?') ? '&' : '?';
            if (! str_contains($back, PrintScreenBackUrl::RETURN_PARAM.'=')) {
                $back .= $separator.http_build_query($returnParams);
            }
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