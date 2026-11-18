@props([
    'rows' => [],
])

<x-invoices.print-info-grid :rows="$rows" {{ $attributes }} />