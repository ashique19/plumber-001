@props(['order'])
<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <h2 class="text-lg font-bold">{{ __('pos.current_order') }}</h2>
    <p class="text-sm text-[var(--app-muted)]">#{{ $order->order_number }}</p>
    @php
        use App\Support\Invoicing\InvoiceLineGrouper;
        $groupedCart = InvoiceLineGrouper::sectionsForDisplay($order->line_items);
    @endphp

    <div class="pos-cart-undo-slot" aria-live="polite">
        @if ($undoLineSnapshot ?? null)
            <div class="pos-cart-undo flex flex-wrap items-center justify-between gap-2 rounded-lg border px-2 py-1.5 text-xs" style="border-color: var(--app-border)">
                <span class="text-[var(--app-muted)]">{{ __('pos.line_removed_toast', ['name' => $undoLineSnapshot['line']['name'] ?? '']) }}</span>
                <button
                    type="button"
                    wire:click="undoRemoveLine"
                    wire:loading.attr="disabled"
                    wire:target="undoRemoveLine"
                    class="pos-cart-undo__btn font-semibold text-[var(--cafe-caramel)] underline"
                >
                    {{ __('pos.undo_remove') }}
                </button>
            </div>
        @endif
    </div>

    <div class="max-h-44 space-y-2 overflow-y-auto sm:max-h-56" wire:loading.class="opacity-60" wire:target="incrementLine,decrementLine,applyLineQuantity,removeLine,undoRemoveLine">
        @if ($order->line_items === [])
            <p class="text-sm text-[var(--app-muted)]">{{ __('pos.empty_cart') }}</p>
        @else
            @foreach ($groupedCart['sections'] as $section)
                @if ($groupedCart['uses_groups'] && ($section['title'] ?? '') !== '')
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">
                        {{ InvoiceLineGrouper::sectionHeading($section, app()->getLocale()) }}
                    </p>
                @endif
                <ul role="list" class="space-y-1">
                    @foreach ($section['lines'] as $line)
                        @php
                            $idx = (int) ($line['_index'] ?? 0);
                            $qty = (int) max(1, floor((float) ($line['quantity'] ?? 1)));
                        @endphp
                        <li
                            class="pos-cart-line flex items-center gap-2 rounded-lg border px-2 py-1.5 text-sm"
                            style="border-color: var(--app-border)"
                            wire:key="pos-cart-line-{{ $order->id }}-{{ $idx }}"
                        >
                            <span class="pos-cart-line__name min-w-0 flex-1 leading-snug">{{ $line['name'] }}</span>

                            <div
                                class="pos-qty-stepper shrink-0"
                                dir="ltr"
                                wire:ignore
                                x-data="posQtyStepper(@js($idx), @js($qty))"
                            >
                                <button
                                    type="button"
                                    class="pos-qty-stepper__btn touch-target"
                                    wire:click="decrementLine({{ $idx }})"
                                    @mousedown.prevent
                                    wire:loading.attr="disabled"
                                    wire:target="decrementLine({{ $idx }})"
                                    aria-label="{{ __('pos.qty_decrease') }}"
                                >
                                    −
                                </button>

                                <button
                                    type="button"
                                    class="pos-qty-stepper__value touch-target"
                                    x-show="!editing"
                                    @click="openEdit()"
                                    aria-label="{{ __('pos.qty_edit') }}"
                                >
                                    <span x-text="displayQty"></span>
                                </button>

                                <input
                                    x-show="editing"
                                    x-cloak
                                    x-ref="qtyInput"
                                    type="number"
                                    min="0"
                                    max="999"
                                    inputmode="numeric"
                                    class="pos-qty-stepper__input field-touch"
                                    x-model.number="draft"
                                    @keydown.enter.prevent="commitEdit()"
                                    @keydown.escape.prevent="onEscape()"
                                    @blur="commitEdit()"
                                />

                                <button
                                    type="button"
                                    class="pos-qty-stepper__btn touch-target"
                                    wire:click="incrementLine({{ $idx }})"
                                    @mousedown.prevent
                                    wire:loading.class="opacity-70"
                                    wire:target="incrementLine({{ $idx }})"
                                    aria-label="{{ __('pos.qty_increase') }}"
                                    @if ($qty >= 999) disabled @endif
                                >
                                    +
                                </button>
                            </div>

                            <button
                                type="button"
                                wire:click="removeLine({{ $idx }})"
                                wire:loading.attr="disabled"
                                wire:target="removeLine({{ $idx }})"
                                class="touch-target pos-cart-line__remove shrink-0 rounded-lg px-2 text-lg font-bold text-red-700"
                                aria-label="{{ __('pos.remove_line') }}"
                            >
                                ×
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>
    <label class="block text-sm font-medium">
        {{ __('pos.promo_discount_ex_vat') }}
        <input id="pos-promo-discount" name="promoDiscount" type="number" step="0.01" min="0" wire:model.blur="promoDiscount" class="field-touch mt-1">
    </label>
    <dl class="app-card app-card--flat space-y-2 p-3 text-sm">
        <div class="flex justify-between gap-2"><dt>{{ __('pos.subtotal_ex_vat') }}</dt><dd class="tabular-nums font-medium">{{ number_format($order->subtotal_ex_vat, 2) }}</dd></div>
        <div class="flex justify-between gap-2"><dt>{{ __('pos.vat') }}</dt><dd class="tabular-nums">{{ number_format($order->vat_total, 2) }}</dd></div>
        <div class="flex justify-between gap-2 border-t pt-2 text-base font-bold" style="border-color: var(--app-border)">
            <dt>{{ __('pos.total_inc_vat') }}</dt>
            <dd class="tabular-nums">{{ number_format($order->total_inc_vat, 2) }} SAR</dd>
        </div>
    </dl>
    <label class="block text-sm font-medium">
        {{ __('pos.payment_method') }}
        <select id="pos-payment-method" wire:model="paymentMethod" class="field-touch mt-1">
                <option value="cash">{{ __('pos.cash') }}</option>
                <option value="card">{{ __('pos.card') }}</option>
                <option value="mada">{{ __('pos.mada') }}</option>
                <option value="other">{{ __('pos.other') }}</option>
            </select>
    </label>
    <div class="space-y-2">
        <x-ui.livewire-action wire:click="pay" target="pay" loading="processing_payment" class="pos-pay-gradient min-h-14 w-full text-base" :disabled="$order->line_items === []">
            {{ __('pos.pay_and_print') }}
        </x-ui.livewire-action>
        <x-ui.livewire-action wire:click="newOrder" target="newOrder" loading="new_order" variant="muted" class="w-full">
            {{ __('pos.new_order') }}
        </x-ui.livewire-action>
    </div>
</div>
