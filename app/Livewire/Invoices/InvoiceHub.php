<?php

namespace App\Livewire\Invoices;

use App\Business\BusinessContext;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Invoice;
use App\Services\Invoicing\HubDraftInvoiceIssueService;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubChronologicalSort;
use App\Support\Hub\HubInvoiceDraftLifecycle;
use App\Support\Hub\HubInvoiceLifecycleTab;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceTableRow;
use App\Support\Hub\HubInvoiceZatcaTab;
use App\Support\Invoicing\PrintScreenBackUrl;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class InvoiceHub extends Component
{
    use DispatchesToasts;
    use HasAdminHubPagination;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = 'all';

    #[Url]
    public string $zatcaTab = HubInvoiceZatcaTab::ALL;

    #[Url(as: 'lifecycle')]
    public string $lifecycleTab = HubInvoiceLifecycleTab::ALL;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $sortBy = 'issued_at';

    #[Url]
    public string $sortDir = 'desc';

    /** @var array<int, bool> */
    public array $resubmitting = [];

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingZatcaTab(): void
    {
        $this->resetPage();
    }

    public function updatingLifecycleTab(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        $allowed = ['document_number', 'issued_at', 'document_type', 'buyer_name'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'issued_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function resubmitInvoice(int $invoiceId, InvoiceZatcaResubmitService $service): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        if ($this->resubmitting[$invoiceId] ?? false) {
            return;
        }

        $this->resubmitting[$invoiceId] = true;

        try {
            $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
            abort_unless(HubInvoiceRowActions::canResendZatca($invoice), 403);

            $result = $service->resubmit($invoice);

            if ($result->invoice && $result->userMessage) {
                $fresh = $result->invoice->fresh();
                match ($result->flashLevel) {
                    'warning' => $this->toastWarning($result->userMessage),
                    'info' => $this->toastInfo($result->userMessage),
                    'error' => $this->toastError($result->userMessage),
                    default => $result->ok
                        ? $this->toastSuccess($result->userMessage)
                        : $this->toastError($result->userMessage),
                };

                if ($result->ok && $this->zatcaTab === HubInvoiceZatcaTab::FAILED && HubInvoiceZatcaTab::isSuccess($fresh)) {
                    $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
                }

                return;
            }

            if ($result->code === 'locked') {
                $this->toastWarning($result->userMessage ?? '');
            } elseif ($result->code === 'already_success') {
                $this->toastInfo($result->userMessage ?? '');
                $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
            } elseif ($result->userMessage) {
                $this->toastError($result->userMessage);
            }
        } finally {
            unset($this->resubmitting[$invoiceId]);
        }
    }

    public function authorizeDraft(int $invoiceId, HubDraftInvoiceIssueService $service): void
    {
        $user = auth('staff')->user();
        abort_unless($user?->can('invoices.issue'), 403);

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        abort_unless(HubInvoiceDraftLifecycle::canAuthorize($user, $invoice), 403);

        $outcome = $service->authorizeAndIssue($invoice, $user);
        if (! $outcome->ok()) {
            $this->toastError($outcome->message ?? __('invoices.errors.issue_failed'));

            return;
        }

        $issued = $outcome->result->invoice;
        if (! $outcome->result->signingSucceeded) {
            $zatcaMessage = filled($outcome->result->signingFailureMessage)
                ? $outcome->result->signingFailureMessage
                : __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]);
            $this->toastWarning($zatcaMessage, persistent: true, dedupe: false);
        }

        $this->toastSuccess(__('invoices.authorize_draft_success', ['number' => $issued->document_number]));
    }

    public function issueDraft(int $invoiceId, HubDraftInvoiceIssueService $service): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        abort_unless(HubInvoiceRowActions::canIssueDraft($invoice), 403);

        $outcome = $service->issueDraft($invoice);
        if (! $outcome->ok()) {
            $this->toastError($outcome->message ?? __('invoices.errors.issue_failed'));

            return;
        }

        $issued = $outcome->result->invoice;
        if (! $outcome->result->signingSucceeded) {
            $zatcaMessage = filled($outcome->result->signingFailureMessage)
                ? $outcome->result->signingFailureMessage
                : __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]);
            $this->toastWarning($zatcaMessage, persistent: true, dedupe: false);
        }

        $this->toastSuccess(__('invoices.issue_success', ['number' => $issued->document_number]));
    }

    public function deleteDraft(int $invoiceId): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        abort_unless(HubInvoiceRowActions::canDeleteDraft($invoice), 403);

        $number = $invoice->document_number;
        $invoice->delete();

        $this->toastSuccess(__('invoices.draft_deleted', ['number' => $number]));
    }

    public function resubmitAllFailed(InvoiceZatcaResubmitService $service): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $invoices = Invoice::query()
            ->where('status', 'issued')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->filter(fn (Invoice $invoice) => HubInvoiceRowActions::canResendZatca($invoice))
            ->take(40);

        $ok = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $result = $service->resubmit($invoice);
            if ($result->ok) {
                $ok++;
            } else {
                $failed++;
            }
        }

        if ($invoices->isEmpty()) {
            $this->toastInfo(__('invoices.zatca_resubmit_bulk_empty'));

            return;
        }

        $this->toastInfo(__('invoices.zatca_resubmit_bulk_done', ['ok' => $ok, 'fail' => $failed]));

        if ($ok > 0 && $failed === 0) {
            $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
        }
    }

    public function render(): View
    {
        $user = auth('staff')->user();
        $canResubmit = ($user?->can('invoices.issue') ?? false)
            && ZatcaSubmissionStatus::canSubmitToZatca($this->business);

        $lifecycleTab = HubInvoiceLifecycleTab::normalize($this->lifecycleTab);
        $this->lifecycleTab = $lifecycleTab;

        return view('livewire.invoices.invoice-hub', [
            'invoices' => $this->invoicePage(),
            'canIssueInvoice' => $user?->can('invoices.issue') ?? false,
            'canRecordPayment' => $user?->can('invoices.issue') ?? false,
            'canResubmitZatca' => $canResubmit,
            'lifecycleTab' => $lifecycleTab,
            'draftCount' => HubInvoiceLifecycleTab::countDrafts(),
            'zatcaFailedCount' => HubInvoiceZatcaTab::countFailed(),
            'zatcaSuccessCount' => HubInvoiceZatcaTab::countSuccess(),
            'zatcaPendingResubmitCount' => HubInvoiceZatcaTab::countPendingResubmit(),
        ]);
    }

    private function invoicePage(): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['referencedInvoice', 'payments'])
            ->whereIn('status', ['issued', 'draft']);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('document_number', 'like', $like)
                    ->orWhere('invoice_hash', 'like', $like)
                    ->orWhere('payload->buyer_name', 'like', $like)
                    ->orWhere('payload->buyer_name_ar', 'like', $like);
            });
        }

        if ($this->typeFilter !== 'all') {
            $query->where('document_type', $this->typeFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('issued_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('issued_at', '<=', $this->dateTo);
        }

        HubInvoiceLifecycleTab::applyScope($query, $this->lifecycleTab);

        if (in_array($this->zatcaTab, [HubInvoiceZatcaTab::FAILED, HubInvoiceZatcaTab::SUCCESS], true)) {
            HubInvoiceZatcaTab::applyTabScope($query, $this->zatcaTab);
        }

        $descending = $this->sortDir !== 'asc';

        if ($this->sortBy === 'buyer_name') {
            $query->orderBy('payload->buyer_name', $descending ? 'desc' : 'asc');
            HubChronologicalSort::applyTieBreakers($query, $descending);
        } elseif ($this->sortBy === 'document_number') {
            $query->orderBy('document_number', $descending ? 'desc' : 'asc');
            HubChronologicalSort::applyTieBreakers($query, $descending);
        } elseif ($this->sortBy === 'document_type') {
            $query->orderBy('document_type', $descending ? 'desc' : 'asc');
            HubChronologicalSort::applyLatestFirst($query, 'issued_at', $descending);
        } else {
            HubChronologicalSort::applyLatestFirst($query, 'issued_at', $descending);
        }

        $paginator = $query->paginate($this->adminPerPage());

        $ledger = app(InvoiceDuesLedgerService::class);
        $returnQuery = PrintScreenBackUrl::encodeHubState([
            'lifecycle' => $this->lifecycleTab,
            'zatcaTab' => $this->zatcaTab,
            'typeFilter' => $this->typeFilter,
        ], PrintScreenBackUrl::INVOICE_HUB_KEYS);

        $paginator->getCollection()->transform(
            fn (Invoice $invoice): array => HubInvoiceTableRow::fromInvoice($invoice, $ledger, $returnQuery),
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [];
    }
}
