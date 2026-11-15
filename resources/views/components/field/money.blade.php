@props(['label', 'name', 'currency' => null])

<x-form.input
    type="number"
    step="0.01"
    inputmode="decimal"
    :label="$label"
    :name="$name"
    :prefix="$currency ?? __('invoices.riyal_symbol')"
    {{ $attributes }}
/>