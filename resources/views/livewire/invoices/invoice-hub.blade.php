<div class="invoice-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('invoices.title') }}</h1>
            <p class="app-page-lead">{{ __('invoices.subtitle') }}</p>
        </div>
    </header>

    <section class="app-card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-form.input :label="__('invoices.search')" name="search" wire:model.live.debounce.300ms="search" :hint="__('invoices.search_hint')" autocomplete="off" wrapperClass="sm:col-span-2" />
        <x-form.select :label="__('invoices.type_filter')" name="typeFilter" wire:model.live="typeFilter">
            <option value="all">{{ __('invoices.filter_all') }}</option>
            <option value="simplified">{{ __('invoices.type.simplified') }}</option>
            <option value="credit_note">{{ __('invoices.type.credit_note') }}</option>
            <option value="debit_note">{{ __('invoices.type.debit_note') }}</option>
            <option value="standard">{{ __('invoices.type.standard') }}</option>
        </x-form.select>
    </section>

    <div class="space-y-2">
        @forelse ($invoices as $invoice)
            @php
                $signingKey = filled($invoice->signed_xml)
                    ? (filled(data_get($invoice->payload, 'zheart_request_id')) ? 'submitted' : 'signed')
                    : 'none';
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
