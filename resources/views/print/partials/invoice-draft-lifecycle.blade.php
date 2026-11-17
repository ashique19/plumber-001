@php
    use App\Support\Hub\HubInvoiceDraftLifecycle;
    use App\Support\Hub\HubInvoiceRowActions;
    /** @var \App\Models\Invoice $invoice */
    $actions = HubInvoiceRowActions::forInvoice($invoice, 0.0);
@endphp

@if (! ($isPdf ?? false) && $invoice->status->value === 'draft')
    <section
        class="invoice-draft-lifecycle no-print mx-2 mb-2 rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-3"
        aria-label="{{ __('invoices.draft_lifecycle_label') }}"
        data-testid="invoice-draft-lifecycle"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            @if ($actions['issue_draft'] ?? false)
                <form
                    method="post"
                    action="{{ route('admin.invoices.drafts.issue', $invoice) }}"
                    class="inline"
                    onsubmit="return confirm(@js(__('invoices.issue_draft_confirm')))"
                >
                    @csrf
                    <button type="submit" class="tap-action-primary min-h-10 px-3 text-sm font-semibold" data-testid="invoice-view-issue-draft">
                        {{ __('invoices.action_issue_draft') }}
                    </button>
                </form>
            @endif
            @if ($actions['edit'] ?? false && filled($actions['edit_url'] ?? null))
                <a class="tap-action-muted min-h-10 px-3 text-sm font-semibold" href="{{ $actions['edit_url'] }}" data-testid="invoice-view-edit-draft">
                    {{ __('invoices.action_edit') }}
                </a>
            @endif
            @if ($actions['delete_draft'] ?? false)
                <form
                    method="post"
                    action="{{ route('admin.invoices.drafts.destroy', $invoice) }}"
                    class="inline"
                    onsubmit="return confirm(@js(__('invoices.delete_draft_confirm')))"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="min-h-10 px-3 text-sm font-semibold text-red-700" data-testid="invoice-view-delete-draft">
                        {{ __('invoices.action_delete_draft') }}
                    </button>
                </form>
            @endif
        </div>
        @if (HubInvoiceDraftLifecycle::wasEverSentOrSignedForZatca($invoice) && HubInvoiceDraftLifecycle::canEdit($invoice))
            <p class="mt-2 mb-0 text-xs text-[var(--app-muted)]">{{ __('invoices.draft_sent_locked_hint') }}</p>
        @endif
    </section>
@endif