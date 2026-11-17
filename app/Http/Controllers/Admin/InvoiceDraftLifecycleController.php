<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\HubDraftInvoiceIssueService;
use App\Support\Hub\HubInvoiceDraftLifecycle;
use App\Support\Hub\HubInvoiceRowActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDraftLifecycleController extends Controller
{
    public function issue(Request $request, Invoice $invoice, HubDraftInvoiceIssueService $service): RedirectResponse
    {
        abort_unless($request->user('staff')?->can('invoices.issue'), 403);
        abort_unless(HubInvoiceDraftLifecycle::canIssue($invoice), 403);

        $outcome = $service->issueDraft($invoice);
        if (! $outcome->ok()) {
            return redirect()
                ->back()
                ->with('error', $outcome->message ?? __('invoices.errors.issue_failed'));
        }

        $issued = $outcome->result->invoice;
        $message = $outcome->result->signingSucceeded
            ? __('invoices.issue_success', ['number' => $issued->document_number])
            : __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]);

        return redirect()
            ->route('admin.invoices')
            ->with($outcome->result->signingSucceeded ? 'success' : 'warning', $message);
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user('staff')?->can('invoices.issue'), 403);
        abort_unless(HubInvoiceRowActions::canDeleteDraft($invoice), 403);

        $number = $invoice->document_number;
        $invoice->delete();

        return redirect()
            ->back()
            ->with('success', __('invoices.draft_deleted', ['number' => $number]));
    }
}