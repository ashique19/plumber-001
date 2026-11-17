@props(['label', 'name', 'currency' => null, 'wrapperClass' => ''])

<x-form.input
    type="number"
    step="0.01"
    inputmode="decimal"
    :label="$label"
    :name="$name"
    :prefix="$currency ?? __('invoices.riyal_symbol')"
    :wrapper-class="$wrapperClass"
    {{ $attributes->except(['label', 'name', 'currency', 'wrapperClass', 'wrapper-class']) }}
/>