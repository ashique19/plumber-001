@props([
    'lines' => [],
    'compact' => false,
])

@php
    $filledCount = collect($lines)->filter(fn ($row) => filled($row['product_id'] ?? null))->count();
@endphp

<section class="invoice-document-lines space-y-3" aria-label="{{ __('invoices.lines_section') }}">
    @foreach ($lines as $index => $line)
        @continue(! filled($line['product_id'] ?? null))
        <article class="invoice-document-lines__row app-inset-panel space-y-2" wire:key="doc-line-{{ $index }}-{{ $line['product_id'] }}">
            <div class="flex items-start justify-between gap-2">
                <h3 class="text-sm font-semibold leading-snug text-[var(--app-fg)]">{{ $line['name'] }}</h3>
                <button
                    type="button"
                    class="tap-action-muted shrink-0 px-2 text-xs font-semibold text-rose-600"
                    wire:click="removeLine({{ $index }})"
                >
                    {{ __('invoices.remove_line') }}
                </button>
            </div>
            @if (filled($line['unit_label'] ?? null))
                <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.line_unit') }}: {{ $line['unit_label'] }}</p>
            @endif
            <div class="grid grid-cols-2 gap-3 {{ $compact ? '' : 'sm:grid-cols-3' }}">
                <x-form.input
                    type="number"
                    step="0.001"
                    min="0.001"
                    :label="__('invoices.line_qty')"
                    name="lines.{{ $index }}.quantity"
                    wire:model.live="lines.{{ $index }}.quantity"
                />
                <x-form.input
                    type="number"
                    step="0.01"
                    min="0"
                    :label="__('invoices.line_price_ex_vat')"
                    name="lines.{{ $index }}.unit_price"
                    wire:model.live="lines.{{ $index }}.unit_price"
                />
                @unless ($compact)
                    <div class="hidden sm:block">
                        <x-form.input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            :label="__('invoices.line_vat')"
                            name="lines.{{ $index }}.vat_rate"
                            wire:model.live="lines.{{ $index }}.vat_rate"
                        />
                    </div>
                @endunless
            </div>
        </article>
    @endforeach

    @if ($filledCount === 0)
        <p class="rounded-xl border border-dashed border-[var(--app-border)] px-4 py-6 text-center text-sm text-[var(--app-muted)]">
            {{ __('invoices.document_lines_empty') }}
        </p>
    @endif
</section>