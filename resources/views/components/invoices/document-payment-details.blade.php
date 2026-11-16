@props([
    'paymentMethodOptions' => [],
    'dueAmount' => 0,
])

<div class="document-create-card">
    <div class="document-create-card__head">
        <x-app.icon name="credit-card" class="h-5 w-5" aria-hidden="true" />
        <span>{{ __('invoices.payment_details') }}</span>
    </div>
    <div class="document-create-card__body space-y-3">
        <x-field.select :label="__('invoices.payment_method_label')" name="paymentMethod" wire:model.live="paymentMethod" required>
            @foreach ($paymentMethodOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-field.select>
        <x-field.money
            step="0.01"
            min="0"
            :label="__('invoices.amount_received')"
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