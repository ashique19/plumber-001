@props([
    'paymentMethodOptions' => [],
    'dueAmount' => 0,
    'paymentMethodRequired' => true,
    'showPaymentFields' => true,
])

@if ($showPaymentFields)
    <div class="document-create-card">
        <div class="document-create-card__head">
            <x-app.icon name="credit-card" class="h-5 w-5" aria-hidden="true" />
            <span>{{ __('invoices.payment_received_section') }}</span>
        </div>
        <div class="document-create-card__body space-y-3">
            <x-field.select
                :label="__('invoices.payment_received_method')"
                name="paymentMethod"
                wire:model.live="paymentMethod"
                :required="$paymentMethodRequired"
            >
                @foreach ($paymentMethodOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-field.select>
            <x-field.money
                step="0.01"
                min="0"
                :label="__('invoices.payment_received_amount')"
                name="amountReceived"
                wire:model.live="amountReceived"
                required
            />
            <p class="document-due-amount m-0">
                {{ __('invoices.due_amount') }}:
                <span class="tabular-nums">{{ number_format((float) $dueAmount, 2) }} {{ __('invoices.riyal_symbol') }}</span>
            </p>
        </div>
    </div>
@endif