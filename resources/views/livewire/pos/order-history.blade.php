<div>
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('pos.history') }}</h1>
        <p class="app-page-lead">{{ $branch->name }}</p>
    </header>
    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 sm:grid-cols-[1fr_minmax(10rem,12rem)]">
        <x-form.input
            :label="__('pos.history_search_label')"
            name="orderSearch"
            type="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('pos.search_orders')"
            :liveValidate="false"
            autocomplete="off"
            wrapperClass="min-w-0"
        />
        <x-form.select
            :label="__('pos.history_status_label')"
            name="orderStatusFilter"
            wire:model.live="statusFilter"
            :liveValidate="false"
            wrapperClass="min-w-0"
        >
            <option value="all">{{ __('pos.filter_all') }}</option>
            <option value="paid">{{ __('pos.filter_paid') }}</option>
            <option value="refunded">{{ __('pos.filter_refunded') }}</option>
            <option value="cancelled">{{ __('pos.filter_cancelled') }}</option>
        </x-form.select>
    </section>
    <div class="grid gap-3 md:hidden">
        @forelse($orders as $order)
            <article class="app-card p-4 text-sm" wire:key="order-card-{{ $order->id }}">
                <p class="font-semibold">#{{ $order->order_number }}</p>
                <span class="pos-status-pill pos-status-pill--{{ $order->status->value }}">{{ $order->status->value }}</span>
                <p class="tabular-nums font-medium">{{ number_format($order->total_inc_vat, 2) }} SAR · {{ $order->payment_method }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if($order->invoice_id)
                        <a class="tap-action-muted min-h-11" href="{{ route('admin.pos.receipt', [ $order]) }}">{{ __('pos.receipt') }}</a>
                        <a class="tap-action-muted min-h-11" href="{{ route('admin.pos.receipt', [ $order, 'autoprint' => 1]) }}">{{ __('pos.print_receipt') }}</a>
                    @endif
                    @if($order->status->value === 'paid')
                        <x-ui.livewire-action wire:click="openRefundModal({{ $order->id }})" :target="'openRefundModal('.$order->id.')'" loading="refund" variant="muted" class="min-h-11">{{ __('pos.refund') }}</x-ui.livewire-action>
                    @endif
                </div>
            </article>
        @empty
            <x-app.empty-state>{{ __('pos.empty_orders') }}</x-app.empty-state>
        @endforelse
    </div>
    <div class="app-table-wrap hidden md:block">
        <table class="app-table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">{{ __('pos.status') }}</th>
                    <th scope="col">{{ __('pos.total_inc_vat') }}</th>
                    <th scope="col">{{ __('pos.payment_method') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('pos.row_actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr wire:key="order-row-{{ $order->id }}">
                        <td>{{ $order->order_number }}</td>
                        <td><span class="pos-status-pill pos-status-pill--{{ $order->status->value }}">{{ $order->status->value }}</span></td>
                        <td class="tabular-nums">{{ number_format($order->total_inc_vat, 2) }}</td>
                        <td>{{ $order->payment_method }}</td>
                        <td class="app-table__actions">
                            @if($order->invoice_id)
                                <a class="tap-action-muted min-h-11 px-3 py-2" href="{{ route('admin.pos.receipt', [ $order]) }}">{{ __('pos.receipt') }}</a>
                                <a class="tap-action-muted min-h-11 px-3 py-2" href="{{ route('admin.pos.receipt', [ $order, 'autoprint' => 1]) }}">{{ __('pos.print_receipt') }}</a>
                            @endif
                            @if($order->status->value === 'paid')
                                <x-ui.livewire-action wire:click="openRefundModal({{ $order->id }})" :target="'openRefundModal('.$order->id.')'" loading="refund" variant="muted" class="ms-1 min-h-11 px-3 py-2">{{ __('pos.refund') }}</x-ui.livewire-action>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>

    @if ($showRefundModal)
        <div class="app-sheet-backdrop" wire:click="cancelRefundModal"></div>
        <div class="app-sheet app-sheet--dialog lg:!inset-auto lg:!start-auto lg:!end-4 lg:!bottom-4 lg:!top-auto lg:!translate-x-0 lg:!translate-y-0" role="dialog" aria-modal="true" aria-labelledby="refund-modal-title">
            <h2 id="refund-modal-title" class="text-lg font-semibold">{{ __('pos.refund_confirm_title') }}</h2>
            <p class="mt-1 text-sm text-[var(--app-muted)]">{{ __('pos.refund_confirm_lead', ['number' => $refundOrderNumber, 'amount' => $refundAmount]) }}</p>
            <form class="mt-4 space-y-3" wire:submit="confirmRefund">
                <x-form.textarea :label="__('pos.refund_reason')" name="refundReason" wire:model="refundReason" rows="3" required />
                <div class="flex flex-wrap gap-2">
                    <x-ui.livewire-action type="submit" target="confirmRefund" loading="refund" class="min-h-11 flex-1">{{ __('pos.refund_confirm') }}</x-ui.livewire-action>
                    <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="cancelRefundModal">{{ __('catalog.cancel') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
