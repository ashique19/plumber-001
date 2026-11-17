<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\StaffUser;
use App\Services\InvoiceIssueService;
use App\Support\Hub\HubInvoiceDraftLifecycle;

final class HubDraftInvoiceIssueService
{
    public function __construct(
        private readonly InvoiceIssueService $issuer,
    ) {}

    public function issueDraft(Invoice $invoice): HubDraftIssueOutcome
    {
        if (! HubInvoiceDraftLifecycle::canIssue($invoice)) {
            return HubDraftIssueOutcome::blocked(__('invoices.draft_issue_not_eligible'));
        }

        if (! HubInvoiceDraftLifecycle::canEdit($invoice)) {
            return HubDraftIssueOutcome::blocked(__('invoices.draft_issue_not_eligible'));
        }

        try {
            $outcome = $this->issuer->issue($invoice->fresh());
        } catch (\Throwable) {
            return HubDraftIssueOutcome::failed(__('invoices.errors.issue_failed'));
        }

        return HubDraftIssueOutcome::issued($outcome);
    }

    public function authorizeAndIssue(Invoice $invoice, StaffUser $actor): HubDraftIssueOutcome
    {
        if (! HubInvoiceDraftLifecycle::canAuthorize($actor, $invoice)) {
            return HubDraftIssueOutcome::blocked(__('invoices.authorize_draft_forbidden'));
        }

        HubInvoiceDraftLifecycle::recordAuthorization($invoice, $actor);

        return $this->issueDraft($invoice->fresh());
    }
}