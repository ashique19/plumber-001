<div class="invoice-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('invoices.title') }}</h1>
            <p class="app-page-lead">{{ __('invoices.subtitle') }}</p>
        </div>
    </header>

    @if ($canIssueInvoice ?? false)
        <section class="app-card mb-4 space-y-3 p-4" aria-label="{{ __('invoices.create_heading') }}">
            <h2 class="text-base font-semibold">{{ __('invoices.create_heading') }}</h2>
            <p class="text-sm text-[var(--app-muted)]">{{ __('invoices.create_print_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <a
                    href="{{ route('admin.invoices.issue', ['mode' => 'standard']) }}"
                    class="app-card tap-action-muted flex min-h-14 flex-col items-start justify-center gap-1 border-2 px-4 py-3 text-start"
                >
                    <span class="text-sm font-semibold">{{ __('invoices.issue_standard_title') }}</span>
                    <span class="text-xs font-medium text-[var(--app-accent)]">{{ __('invoices.print_a4') }}</span>
                    <span class="text-xs text-[var(--app-muted)]">{{ __('invoices.layout_a4') }}</span>
                </a>
                <a
                    href="{{ route('admin.invoices.issue', ['mode' => 'simplified']) }}"
                    class="app-card tap-action-muted flex min-h-14 flex-col items-start justify-center gap-1 border-2 px-4 py-3 text-start"
                >
                    <span class="text-sm font-semibold">{{ __('invoices.issue_simplified_title') }}</span>
                    <span class="text-xs font-medium text-[var(--app-accent)]">{{ __('invoices.print_cash') }}</span>
                    <span class="text-xs text-[var(--app-muted)]">{{ __('invoices.layout_cash') }}</span>
                </a>
            </div>
        </section>
    @endif

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-field.input
            :label="__('invoices.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('invoices.search_hint')"
            :hint="null"
            :liveValidate="false"
            autocomplete="off"
            wrapperClass="min-w-0 sm:col-span-2"
        />
        <x-field.select
            :label="__('invoices.type_filter')"
            name="typeFilter"
            wire:model.live="typeFilter"
            :liveValidate="false"
            wrapperClass="min-w-0"
        >
            <option value="all">{{ __('invoices.filter_all') }}</option>
            <option value="simplified">{{ __('invoices.type.simplified') }}</option>
            <option value="credit_note">{{ __('invoices.type.credit_note') }}</option>
            <option value="debit_note">{{ __('invoices.type.debit_note') }}</option>
            <option value="standard">{{ __('invoices.type.standard') }}</option>
        </x-field.select>
    </section>

    <div class="space-y-2">
        @forelse ($invoices as $invoice)
            @php
                $zatcaNotSent = \App\Support\Invoicing\ZatcaSubmissionStatus::invoiceNotSentToZatca($invoice);
                $signingKey = $zatcaNotSent
                    ? 'zatca_not_sent'
                    : (filled($invoice->signed_xml)
                        ? (filled(data_get($invoice->payload, 'zheart_zatca'))
                            || data_get($invoice->payload, 'zheart_remote_submitted')
                            ? 'submitted'
                            : 'signed')
                        : 'none');
                $order = $this->orderFor($invoice);
            @endphp
            <article class="app-list-card" wire:key="inv-{{ $invoice->id }}">
                <div>
                    <p class="font-semibold">{{ $invoice->document_number }}</p>
                    <p class="text-xs text-[var(--app-muted)]">
                        {{ __('invoices.type.'.$invoice->document_type->value) }}
                        · {{ $invoice->issued_at?->format('Y-m-d H:i') ?? '—' }}
                        @if ($invoice->referencedInvoice)
                            · {{ __('invoices.ref') }} {{ $invoice->referencedInvoice->document_number }}
                        @endif
                        @if ($order)
                            · {{ __('invoices.order') }} {{ $order->order_number }}
                        @endif
                    </p>
                    <p class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span @class([
                            'pos-status-pill',
                            'pos-status-pill--paid' => in_array($signingKey, ['submitted', 'signed'], true),
                            'pos-status-pill--refunded' => $signingKey === 'none',
                            'pos-status-pill--warning' => $signingKey === 'zatca_not_sent',
                        ])>{{ __('invoices.signing.'.$signingKey) }}</span>
                        @if ($invoice->document_type->value === 'credit_note')
                            <span class="pos-status-pill pos-status-pill--cancelled">{{ __('invoices.credit_badge') }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($order)
                        <a class="tap-action-muted min-h-11 px-4 text-sm" href="{{ route('admin.pos.history', [$order->branch_id]) }}">{{ __('invoices.view_order') }}</a>
                    @endif
                    <a class="tap-action-muted min-h-11 px-4 text-sm" href="{{ route('admin.invoices.print', [$invoice]) }}" target="_blank" rel="noopener">{{ __('invoices.print') }}</a>
                </div>
            </article>
        @empty
            <p class="app-card p-6 text-center text-sm text-[var(--app-muted)]">{{ __('invoices.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</div>
